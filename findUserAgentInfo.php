<?php

/**
 * findUserAgentInfo Plugin for LimeSurvey
 * Fill an answer with some user agent information
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2014-2021 Denis Chenu <http://sondages.pro>
 * @copyright 2014 Validators <http://validators.nl>
 * @license AGPL v3
 * @version 4.0.1
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * This plugin use :
 * Browser.php : Copyright (C) 2008-2010 Chris Schuld  (chris@chrisschuld.com) - (http://chrisschuld.com/)
 */
class findUserAgentInfo extends PluginBase
{
    protected $storage = 'DbStorage';

    protected static $description = 'A plugin to find some User agent information.';
    protected static $name = 'Get userAgent information';

    protected $settings = array(
        'browsercode' => array(
            'type' => 'string',
            'label' => 'The question code to be filled with browser information.',
            'default' => 'browser'
        ),
        'browsernamecode' => array(
            'type' => 'string',
            'label' => 'The question code to be filled with browser name.',
            'default' => 'browsername'
        ),
        'browserversioncode' => array(
            'type' => 'string',
            'label' => 'The question code to be filled with browser major version (integer value).',
            'default' => 'browserversion'
        ),
        'browserplatform' => array(
            'type' => 'string',
            'label' => 'The question code to be filled with browser platform (OS).',
            'default' => 'browserplatform'
        ),
        'browserismobile' => array(
            'type' => 'string',
            'label' => 'The question code to be filled with is mobile (Y for mobile else N).',
            'default' => 'browserismobile'
        ),
        'browseristablet' => array(
            'type' => 'string',
            'label' => 'The question code to be filled with is tablet (Y for tablet else N).',
            'default' => 'browseristablet'
        ),
        'browserisrobot' => array(
            'type' => 'string',
            'label' => 'The question code to be filled with is robot (Y for robot else N).',
            'default' => 'browserisrobot'
        ),
        'active' => array(
            'type' => 'boolean',
            'label' => 'Use it by default.',
            'default' => 1
        ),
        'questioncodeexample' => array(
            'type' => 'info',
            'content' => '<div class="alert alert-info">'
                        . '<p>You have an survey exemple file in the plugin directory (limesurvey_survey_browser.lss).</p>'
                        . '<p>TYou can find all code used <a href="https://gitlab.com/SondagesPro/QuestionSettingsType/findUserAgentInfo/-/blob/master/Browser/Browser.php#L56">in Browser.php</a> </p>'
                        . '</div>',
        ),
    );

    public function init()
    {
        $this->subscribe('beforeSurveyPage');

        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');
    }

    public function beforeSurveySettings()
    {
        $event = $this->event;
        $default = $this->get('active', null, null, 1);
        $defaultString = $default ? gt("Yes") : gt("No");
        $event->set("surveysettings.{$this->id}", array(
            'name' => get_class($this),
            'settings' => array(
                'active' => array(
                    'type' => 'select',
                    'label' => 'Use findUserAgentInfo plugin',
                    'options' => array(
                        '0' => gt("No"),
                        '1' => gt("Yes"),
                    ),
                    'htmlOptions' => array(
                        'empty' => sprintf("Use default (%s)", $defaultString),
                    ),
                    'current' => $this->get('active', 'Survey', $event->get('survey'), null),
                ),
            )
        ));
    }
    public function newSurveySettings()
    {
        $event = $this->event;
        foreach ($event->get('settings') as $name => $value) {
            $this->set($name, $value, 'Survey', $event->get('survey'), '');
        }
    }
    public function beforeSurveyPage()
    {
        $oEvent = $this->getEvent();
        $iSurveyId = $oEvent->get('surveyId');
        $oSurvey = Survey::model()->findByPk($iSurveyId);
        if (empty($oSurvey)) {
            return;
        }
        $bActive = $this->get('active', 'Survey', $iSurveyId, '');
        if ($bActive === '') {
            $bActive = $this->get('active', null, null, $this->settings['active']['default']);
        }
        if (!$bActive) {
            return;
        }
        $this->setSurveySessionBrowser($iSurveyId);
        $this->setSurveySessionBrowser($iSurveyId);
    }

    /**
    * get the array of browser code updated
    * @param $iSurveyId : actual survey id
    *
    * @return void
    */
    private function setSurveySessionBrowser($surveyId)
    {
        /* before Survey page happen after newtest=Y and SetSurveyLanguage */
        $sessionSurvey = App()->session["survey_{$surveyId}"];
        if (!empty($sessionSurvey['startingValues'])) {
            /* Already started */
            return;
        }
        $sessionSurvey['startingValues'] = array();
        /* Check if have one question for this survey */
        $questionTitles = array_filter(array(
            'browsercode' => $this->get('browsercode', null, null, $this->settings['browsercode']['default']),
            'browsernamecode' => $this->get('browsernamecode', null, null, $this->settings['browsernamecode']['default']),
            'browserversioncode' => $this->get('browserversioncode', null, null, $this->settings['browserversioncode']['default']),
            'browserplatform' => $this->get('browserplatform', null, null, $this->settings['browserplatform']['default']),
            'browserismobile' => $this->get('browserismobile', null, null, $this->settings['browserismobile']['default']),
            'browseristablet' => $this->get('browseristablet', null, null, $this->settings['browseristablet']['default']),
            'browserisrobot' => $this->get('browserisrobot', null, null, $this->settings['browserisrobot']['default']),
        ));
        $criteria = new CDbCriteria();
        $criteria->compare('sid', $surveyId);
        $criteria->compare('parent_qid', 0);
        $criteria->addInCondition('title', $questionTitles);
        $criteria->addInCondition('type', array('S', 'L'));

        $oQuestions = Question::model()->findAll($criteria);
        if (empty($oQuestions)) {
            return;
        }
        $sessionUserAgent = $this->getSessionUserAgent();
        foreach ($oQuestions as $oQuestion) {
            $sQuestionSGQ = $oQuestion->sid . "X" . $oQuestion->gid . "X" . $oQuestion->qid;
            $sBrowserCode = array_search($oQuestion->title, $questionTitles);
            switch ($sBrowserCode) {
                case 'browsernamecode':
                    $completeValue = $sessionUserAgent['Browser'];
                    break;
                case 'browserversioncode':
                    $completeValue = $sessionUserAgent['MajorVersion'];
                    break;
                case 'browserplatform':
                    $completeValue = $sessionUserAgent['Platform'];
                    break;
                case 'browserismobile':
                    $completeValue = $sessionUserAgent['isMobile'];
                    break;
                case 'browseristablet':
                    $completeValue = $sessionUserAgent['isTablet'];
                    break;
                case 'browserisrobot':
                    $completeValue = $sessionUserAgent['isRobot'];
                    break;
                case 'browsercode':
                default:
                    $completeValue = $sessionUserAgent['Browser'] . " " . $sessionUserAgent['MajorVersion'];
                    break;
            }
            if ($oQuestion->type == 'S') {
                $sessionSurvey['startingValues'][$sQuestionSGQ] = $completeValue;
                $sessionSurvey[$sQuestionSGQ] = $completeValue;
            }
            if ($oQuestion->type == 'L') {
                switch ($sBrowserCode) {
                    case 'browseristablet':
                    case 'browserisrobot':
                        $oAnswer = Answer::model()->find("qid=:qid and code=:code", array(':qid' => $oQuestion->qid,':code' => $completeValue));
                        if ($oAnswer) {
                            break;
                        }
                    case 'browsernamecode':
                    case 'browserplatform':
                    case 'browserismobile':
                        if (intval(App()->getConfig('versionnumber')) <= 3) {
                            $oAnswer = Answer::model()->find("qid=:qid and answer=:answer", array(':qid' => $oQuestion->qid,':answer' => $completeValue));
                        } else {
                            $oAnswer = Answer::model()->with('answerl10ns')->find(
                                "qid=:qid and answer=:answer",
                                array(':qid' => $oQuestion->qid,':answer' => $completeValue)
                            );
                        }
                        break;
                    case 'browserversioncode':
                        if (intval(App()->getConfig('versionnumber')) <= 3) {
                            $oAnswer = Answer::model()->find(array(
                                'condition' => "qid=:qid and answer<=:answer",
                                'order' => "cast(answer as unsigned) desc", /* Same for all SQL ?*/
                                'params' => array(':qid' => $oQuestion->qid,':answer' => $completeValue)
                            ));
                        } else {
                            $oAnswer = Answer::model()->with('answerl10ns')->find(array(
                                'condition' => "qid=:qid and answer<=:answer",
                                'order' => "cast(answer as unsigned) desc", /* Same for all SQL ?*/
                                'params' => array(':qid' => $oQuestion->qid,':answer' => $completeValue)
                            ));
                        }
                        break;
                    case 'browsercode':
                    default:
                        if (intval(App()->getConfig('versionnumber')) <= 3) {
                            $oAnswer = Answer::model()->find("qid=:qid and answer=:answer", array(':qid' => $oQuestion->qid,':answer' => $completeValue));
                        } else {
                            $oAnswer = Answer::model()->with('answerl10ns')->find(
                                "qid=:qid and answer=:answer",
                                array(':qid' => $oQuestion->qid,':answer' => $completeValue)
                            );
                        }
                        if (!$oAnswer) {
                            if (intval(App()->getConfig('versionnumber')) <= 3) {
                                $oAnswer = Answer::model()->find("qid=:qid and answer=:answer", array(':qid' => $oQuestion->qid,':answer' => $sessionUserAgent['Browser']));
                            } else {
                                $oAnswer = Answer::model()->with('answerl10ns')->find(
                                    "qid=:qid and answer=:answer",
                                    array(':qid' => $oQuestion->qid,':answer' => $completeValue)
                                );
                            }
                        }
                        break;
                }
                if ($oAnswer) {
                    $sessionSurvey['startingValues'][$sQuestionSGQ] = $oAnswer->code;
                    $sessionSurvey[$sQuestionSGQ] = $oAnswer->code;
                } elseif ($oQuestion->other == "Y") {
                    $sessionSurvey['startingValues'][$sQuestionSGQ] = "-oth-";
                    $sessionSurvey[$sQuestionSGQ] = "-oth-";
                    $sessionSurvey['startingValues']["{$sQuestionSGQ}other"] = $completeValue;
                    $sessionSurvey["{$sQuestionSGQ}other"] = $completeValue;
                } else {
                    $this->log("Browser {$sBrowserCode} : {$completeValue} not found in answers", 'warning');
                }
            }
        }
        App()->session["survey_{$surveyId}"] = $sessionSurvey;
    }

    /**
    * get the array with browser information
    * Using $_SESSION to don't search again and again because the browser can not change during $_SESSION
    *
    * return array
    */
    private function getSessionUserAgent()
    {
        $sessionUserAgent = Yii::app()->session['UserAgentInfo'];
        if (empty($sessionUserAgent['Browser'])) {
            $basedir = dirname(__FILE__); // this will give you the / directory
            Yii::setPathOfAlias('finduseragentinfo', $basedir);
            Yii::import('finduseragentinfo.Browser.Browser');
            $browser = new Browser();
            $sessionUserAgent['Platform'] = $browser->getPlatform();
            $sessionUserAgent['Browser'] = $browser->getBrowser();
            $sessionUserAgent['Version'] = $browser->getVersion();
            $sessionUserAgent['MajorVersion'] = intval($browser->getVersion());
            $sessionUserAgent['isMobile'] = $browser->isMobile() ? "Y" : "N";
            $sessionUserAgent['isTablet'] = $browser->isTablet() ? "Y" : "N";
            $sessionUserAgent['isRobot'] = $browser->isRobot() ? "Y" : "N";
        }
        Yii::app()->session['UserAgentInfo'] = $sessionUserAgent;
        return $sessionUserAgent;
    }

    /**
     * Translating
     * @var string
     * @return string
     */
    public function gT($string, $sEscapeMode = 'unescaped', $sLanguage = null)
    {
        if (Yii::app()->getConfig('versionnumber') >= 3) {
            return parent::gT($string, $sEscapeMode, $sLanguage);
        }
        return gT($string, 'unescaped', $sLanguage);
    }
}
