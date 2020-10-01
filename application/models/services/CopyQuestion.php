<?php


namespace LimeSurvey\Models\Services;

use LimeSurvey\Datavalueobjects\CopyQuestionValues;

/**
 * Class CopyQuestion
 *
 * This class is responsible for the copy question process.
 *
 * @package LimeSurvey\Models\Services
 */
class CopyQuestion
{

    /**
     * @var CopyQuestionValues values needed to copy a question (e.g. questioncode, questionGroupId ...)
     */
    private $copyQuestionValues;

    /**
     * @var \Question the new question
     */
    private $newQuestion;

    /**
     * CopyQuestion constructor.
     *
     * @param CopyQuestionValues $copyQuestionValues
     */
    public function __construct($copyQuestionValues)
    {
        $this->copyQuestionValues = $copyQuestionValues;
        $this->newQuestion = null;
    }

    /**
     * Copies the question and all necessary values/parameters
     * (languages, subquestions, answeroptions, defaultanswers, settings)
     *
     * @param array $copyOptions has the following boolean elements
     *                          ['copySubquestions']
     *                          ['copyAnswerOptions']
     *                          ['copyDefaultAnswers']
     *                          ['copySettings'] --> generalSettings and advancedSettings
     *
     * @return true if new copied question could be saved, false otherwise
     */
    public function copyQuestion($copyOptions)
    {
        $copySuccessful = $this->createNewCopiedQuestion(
            $this->copyQuestionValues->getQuestionCode(),
            $this->copyQuestionValues->getQuestionGroupId(),
            $this->copyQuestionValues->getQuestiontoCopy()
        );
        if ($copySuccessful) {
            //copy question languages
            $this->copyQuestionLanguages($this->copyQuestionValues->getQuestiontoCopy());

            //copy subquestions
            if ($copyOptions['copySubquestions']) {
                $this->copyQuestionsSubQuestions($this->copyQuestionValues->getQuestiontoCopy()->qid);
            }

            //copy answer options
            if ($copyOptions['copyAnswerOptions']) {
                $this->copyQuestionsAnswerOptions($this->copyQuestionValues->getQuestiontoCopy()->qid);
            }

            //copy default answers
            if ($copyOptions['copyDefaultAnswers']) {
                $this->copyQuestionsDefaultAnswers($this->copyQuestionValues->getQuestiontoCopy()->qid);
            }

            ////copy question settings (generalsettings and advanced settings)
            if ($copyOptions['copySettings']) {
                $this->copyQuestionsSettings($this->copyQuestionValues->getQuestiontoCopy()->qid);
            }
        }
        return $copySuccessful;
    }


    /**
     * Creates a new question copying the values from questionToCopy
     *
     * @param string $questionCode
     * @param int $groupId
     * @param \Question $questionToCopy the question that should be copied
     *
     * @return bool true if question could be saved, false otherwise
     */
    public function createNewCopiedQuestion($questionCode, $groupId, $questionToCopy)
    {
        $this->newQuestion = new \Question();
        $this->newQuestion->attributes = $questionToCopy->attributes;
        $this->newQuestion->title = $questionCode;
        $this->newQuestion->gid = $groupId;
        $this->newQuestion->qid = null;

        return $this->newQuestion->save();
    }

    /**
     * Copies the languages of a question.
     *
     * @param \Question $oQuestion old question from where to copy the languages (see table questions_l10ns)
     *
     * @before $this->newQuestion must exist and should not be null
     *
     * @return bool true if all languages could be copied,
     *              false if no language was copied or save failed for one language
     */
    private function copyQuestionLanguages($oQuestion)
    {
        $allLanguagesAreCopied = false;
        if ($oQuestion !== null) {
            $allLanguagesAreCopied = true;
            foreach ($oQuestion->questionl10ns as $sLanguage) {
                $copyLanguage = new \QuestionL10n();
                $copyLanguage->attributes = $sLanguage->attributes;
                $copyLanguage->id = null; //new id needed
                $copyLanguage->qid = $this->newQuestion->qid;
                $allLanguagesAreCopied = $allLanguagesAreCopied && $copyLanguage->save();
            }
        }

        return $allLanguagesAreCopied;
    }

    /**
     * Copy subquestions of a question
     *
     * @param int $parentId id of question to be copied
     *
     * * @before $this->newQuestion must exist and should not be null
     *
     * @return bool true if all subquestions could be copied&saved, false if a subquestion could not be saved
     */
    private function copyQuestionsSubQuestions($parentId)
    {
        //copy subquestions
        $areSubquestionsCopied = true;
        $subquestions = \Question::model()->findAllByAttributes(['parent_qid' => $parentId]);

        foreach ($subquestions as $subquestion) {
            $copiedSubquestion = new \Question();
            $copiedSubquestion->attributes = $subquestion->attributes;
            $copiedSubquestion->parent_qid = $this->newQuestion->qid;
            $copiedSubquestion->qid = null; //new question id needed ...
            $areSubquestionsCopied = $areSubquestionsCopied && $copiedSubquestion->save();
            foreach ($subquestion->questionl10ns as $subquestLanguage) {
                $newSubquestLanguage = new \QuestionL10n();
                $newSubquestLanguage->attributes = $subquestLanguage->attributes;
                $newSubquestLanguage->qid = $copiedSubquestion->qid;
                $newSubquestLanguage->id = null;
                $newSubquestLanguage->save();
            }
        }

        return $areSubquestionsCopied;
    }

    /**
     * Copies the answer options of a question
     *
     * * @before $this->newQuestion must exist and should not be null
     *
     * @param int $questionIdToCopy
     */
    private function copyQuestionsAnswerOptions($questionIdToCopy)
    {
        $answerOptions = \Answer::model()->findAllByAttributes(['qid' => $questionIdToCopy]);
        foreach ($answerOptions as $answerOption) {
            $copiedAnswerOption = new \Answer();
            $copiedAnswerOption->attributes = $answerOption->attributes;
            $copiedAnswerOption->aid = null;
            $copiedAnswerOption->qid = $this->newQuestion->qid;
            if ($copiedAnswerOption->save()) {
                //copy the languages
                foreach ($answerOption->answerl10ns as $answerLanguage) {
                    $copiedAnswerOptionLanguage = new \AnswerL10n();
                    $copiedAnswerOptionLanguage->attributes = $answerLanguage->attributes;
                    $copiedAnswerOptionLanguage->id = null;
                    $copiedAnswerOptionLanguage->aid = $copiedAnswerOption->aid;
                    $copiedAnswerOptionLanguage->save();
                }
            }
        }
    }

    /**
     * Copies the default answers of the question
     *
     * * @before $this->newQuestion must exist and should not be null
     *
     * @param int $questionIdToCopy
     */
    private function copyQuestionsDefaultAnswers($questionIdToCopy)
    {
        $defaultAnswers = \DefaultValue::model()->findAllByAttributes(['qid' => $questionIdToCopy]);
        foreach ($defaultAnswers as $defaultAnswer) {
            $copiedDefaultAnswer = new \DefaultValue();
            $copiedDefaultAnswer->attributes = $defaultAnswer->attributes;
            $copiedDefaultAnswer->qid = $this->newQuestion->qid;
            $copiedDefaultAnswer->dvid = null;
            if ($copiedDefaultAnswer->save()) {
                //copy languages if needed
                $defaultValLanguages = \DefaultValueL10n::model()->findAllByAttributes(['dvid' => $defaultAnswer->dvid]);
                foreach ($defaultValLanguages as $defaultAnswerL10n) {
                    $copieDefaultAnswerLanguage = new \DefaultValueL10n();
                    $copieDefaultAnswerLanguage->attributes = $defaultAnswerL10n->attributes;
                    $copieDefaultAnswerLanguage->dvid = $copiedDefaultAnswer->dvid;
                    $copieDefaultAnswerLanguage->id = null;
                    $copieDefaultAnswerLanguage->save();
                }
            }
        }
    }

    /**
     * Copies the question settings (general_settings (on the left in questioneditor) and advanced settings (bottom)
     *
     * @param $questionIdToCopy
     *
     * * @before $this->newQuestion must exist and should not be null
     *
     * @return true if settings are copied, false otherwise
     */
    private function copyQuestionsSettings($questionIdToCopy)
    {
        $settingsFromQuestionToCopy = \QuestionAttribute::model()->findAllByAttributes(['qid' => $questionIdToCopy]);
        $areSettingsCopied = false;
        if ($this->newQuestion !== null) {
            $areSettingsCopied = true;
            foreach ($settingsFromQuestionToCopy as $settingToCopy) {
                $newSetting = new \QuestionAttribute();
                $newSetting->attributes = $settingToCopy->attributes;
                $newSetting->qaid = null;  //create new id
                $newSetting->qid = $this->newQuestion->qid;
                $areSettingsCopied = $areSettingsCopied && $newSetting->save();
            }
        }

        return $areSettingsCopied;
    }

    /**
     * Returns the new created question or null if question was not copied.
     *
     * @return \Question|null
     */
    public function getNewCopiedQuestion()
    {
        return $this->newQuestion;
    }
}