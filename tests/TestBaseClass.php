<?php

namespace ls\tests;

use PHPUnit\Framework\TestCase;

class TestBaseClass extends TestCase
{
    /**
     * @var TestHelper
     */
    protected static $testHelper = null;

    /** @var  string $tempFolder*/
    protected static $tempFolder;

    /** @var  string $screenshotsFolder */
    protected static $screenshotsFolder;

    /** @var  string $surveysFolder */
    protected static $surveysFolder;

    /** @var  string $dataFolder */
    protected static $dataFolder;

    /** @var  string $viewsFolder */
    protected static $viewsFolder;

    /** @var  \Survey */
    protected static $testSurvey;

    /** @var  integer */
    protected static $surveyId;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        // Clear database cache.
        \Yii::app()->db->schema->refresh();

        //$lt = ini_get('session.gc_maxlifetime');
        //var_dump('gc_maxlifetime = ' . $lt);
        //die;

        // This did not fix the langchang test failure on Travis.
        //session_destroy();
        //session_start();

        self::$testHelper = new TestHelper();

        self::$dataFolder = __DIR__.'/data';
        self::$viewsFolder = self::$dataFolder."/views";
        self::$surveysFolder = self::$dataFolder.'/surveys';
        self::$tempFolder = __DIR__.'/tmp';
        self::$screenshotsFolder = self::$tempFolder.'/screenshots';
        self::$testHelper->importAll();

        \Yii::import('application.helpers.globalsettings_helper', true);
    }

    /**
     * @param string $fileName
     * @return void
     */
    protected static function importSurvey($fileName)
    {
        \Yii::app()->session['loginID'] = 1;
        $surveyFile = $fileName;
        if (!file_exists($surveyFile)) {
            throw new \Exception(sprintf('Survey file %s not found',$surveyFile));
        }

        $translateLinksFields = false;
        $newSurveyName = null;
        $result = \importSurveyFile(
            $surveyFile,
            $translateLinksFields,
            $newSurveyName,
            null
        );
        if ($result) {
            \Survey::model()->resetCache(); // Reset the cache so findByPk doesn't return a previously cached survey
            self::$testSurvey = \Survey::model()->findByPk($result['newsid']);
            self::$surveyId = $result['newsid'];
        } else {
            throw new \Exception(sprintf('Failed to import survey file %s',$surveyFile));
        }
    }

    /**
     * Get all question inside current survey, key is question code
     * @return array[]
     */
    public function getAllSurveyQuestions()
    {
        if(empty(self::$surveyId)) {
            throw new \Exception('getAllSurveyQuestions call without survey.');
        }
        $survey = \Survey::model()->findByPk(self::$surveyId);
        if(empty($survey)) {
            throw new \Exception('getAllSurveyQuestions call with an invalid survey.');
        }
        $questions = [];
        foreach($survey->groups as $group) {
            $questionObjects = $group->questions;
            foreach ($questionObjects as $q) {
                $questions[$q->title] = $q;
            }
        }
        return $questions;
    }

    /**
     * @return void
     */
    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();

        // Make sure we have permission to delete survey.
        \Yii::app()->session['loginID'] = 1;

        if (self::$testSurvey) {
            if (!self::$testSurvey->delete()) {
                self::assertTrue(
                    false,
                    'Fatal error: Could not clean up survey '
                    . self::$testSurvey->sid
                    . '; errors: '
                    . json_encode(self::$testSurvey->errors)
                );
            }
            self::$testSurvey = null;
        }
    }

    /**
     * Helper install and activate plugins by name
     * @param string $pluginName
     * @return void
     */
    public static function installAndActivatePlugin($pluginName)
    {
        $plugin = \Plugin::model()->findByAttributes(array('name'=>$pluginName));
        if (!$plugin) {
            $plugin = new \Plugin();
            $plugin->name = $pluginName;
            $plugin->active = 1;
            $plugin->save();
        } else {
            $plugin->active = 1;
            $plugin->save();
        }
    }

    /**
     * Helper dactivate plugins by name
     * @param string $pluginName
     * @return void
     */
    public static function deActivatePlugin($pluginName)
    {
        $plugin = \Plugin::model()->findByAttributes(array('name'=>$pluginName));
        if ($plugin) {
            $plugin->active = 0;
            $plugin->save();
        }
    }
}
