<?php
/**
 * findUserAgentInfo Plugin for LimeSurvey
 * Fill an answer with some user agent information
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2014-2018 Denis Chenu <http://sondages.pro>
 * @copyright 2014 Validators <http://validators.nl>
 * @license AGPL v3
 * @version 3.0.0
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
class findUserAgentInfo extends PluginBase {
    protected $storage = 'DbStorage';

    static protected $description = 'A plugin to find some User agent information.';
    static protected $name = 'Get userAgent information';

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
            'label' => 'The question code to be filled with browser version.',
            'default' => 'browserversion'
        ),
        'active' => array(
            'type' => 'boolean',
            'label' => 'Use it by default.',
            'default' => true
        ),
        'questioncodeexample' => array(
            'type' => 'info',
            'content' => '<div class="alert alert-info">You have an survey exemple file in the plugin directory (limesurvey_survey_browser.lss).</div>',
        ),
    );

    public function init() {
        $this->subscribe('beforeSurveyPage');

        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');

    }

    public function beforeSurveySettings()
    {
        $event = $this->event;
        $event->set("surveysettings.{$this->id}", array(
            'name' => get_class($this),
            'settings' => array(
                'active'=>array(
                    'type'=>'select',
                    'label'=>'Use findUserAgentInfo plugin',
                    'options'=>array(
                        '0'=>gt("No"),
                        '1'=>gt("Yes"),
                    ),
                    'htmlOptions'=>array(
                        'empty'=>"Use default (".")",
                    ),
                    'current' => $this->get('active', 'Survey', $event->get('survey'),null),
                ),
            )
        ));
    }
    public function newSurveySettings()
    {
        $event = $this->event;
        foreach ($event->get('settings') as $name => $value)
        {
            /* In order use survey setting, if not set, use global, if not set use default */
            $default=$event->get($name,null,null,isset($this->settings[$name]['default'])?$this->settings[$name]['default']:NULL);
            $this->set($name, $value, 'Survey', $event->get('survey'),$default);
        }
    }
    public function beforeSurveyPage()
    {
        $oEvent=$this->getEvent();
        $iSurveyId=$oEvent->get('surveyId');

        $oSurvey=Survey::model()->findByPk($iSurveyId);
        $bActive=$this->get('active', 'Survey', $iSurveyId,'');
        if($bActive===''){
            $bActive=$this->get('active', null, null,$this->settings['active']['default']);
        }
        if($oSurvey && $bActive)
        {
            $this->setSessionBrowserCode('browsercode');
            $this->setSessionBrowserCode('browsernamecode');
            $this->setSessionBrowserCode('browserversioncode');
        }
    }

    /**
    * get the array of browser code updated
    * @param $iSurveyId : actual survey id
    * @param $sBrowserCode : actual browser code
    *
    * @return void
    */
    private function setSessionBrowserCode($sBrowserCode)
    {
        $iSurveyId=$this->event->get('surveyId');
        $sessionSurvey=Yii::app()->session["survey_{$iSurveyId}"];
        $sessionUserAgent=$this->getSessionUserAgent();

        $sQuestioncode=$this->get($sBrowserCode,null,null,$this->settings[$sBrowserCode]['default']);
        $oQuestionBrowser=Question::model()->find("sid=:sid and title=:title and parent_qid=0",array(':sid'=>$iSurveyId,':title'=>$sQuestioncode));

        if(!isset($sessionSurvey['startingValues']) && $oQuestionBrowser && ($oQuestionBrowser->type=='S' or $oQuestionBrowser->type=='L'))
        {
            $sQuestionId=$oQuestionBrowser->sid."X".$oQuestionBrowser->gid."X".$oQuestionBrowser->qid;
            if(empty($sessionSurvey[$sQuestionId])){// Don't replace existing answer
                /* value to set depend on  order of answer */
                switch ($sBrowserCode){
                    case 'browsernamecode':
                        $completeValue=$sessionUserAgent['Browser'];
                        break;
                    case 'browserversioncode':
                        $completeValue=$sessionUserAgent['MajorVersion'];
                        break;
                    case 'browsercode':
                    default:
                        $completeValue=$sessionUserAgent['Browser']." ".$sessionUserAgent['MajorVersion'];
                        break;
                }
                if($oQuestionBrowser->type=='S'){
                    $_GET[$sQuestionId]=$completeValue;
                }elseif($oQuestionBrowser->type=='L'){
                    /* search order of answer */
                    switch ($sBrowserCode){
                        case 'browsernamecode':
                            $oAnswer=Answer::model()->find("qid=:qid and answer=:answer",array(':qid'=>$oQuestionBrowser->qid,':answer'=>$completeValue));
                            break;
                        case 'browserversioncode':
                            $oAnswer=Answer::model()->find(array(
                                'condition'=>"qid=:qid and answer<=:answer",
                                'order'=>"cast(answer as unsigned) desc", /* Same for all SQL ?*/
                                'params'=>array(':qid'=>$oQuestionBrowser->qid,':answer'=>$completeValue)
                                ));
                            break;
                        case 'browsercode':
                        default:
                            $oAnswer=Answer::model()->find("qid=:qid and answer=:answer",array(':qid'=>$oQuestionBrowser->qid,':answer'=>$completeValue));
                            if(!$oAnswer){
                                $oAnswer=Answer::model()->find("qid=:qid and answer=:answer",array(':qid'=>$oQuestionBrowser->qid,':answer'=>$sessionUserAgent['Browser']));
                            }
                            break;
                    }
                    if($oAnswer){
                        $_GET[$sQuestionId]=$oAnswer->code;
                    }elseif($oQuestionBrowser->other=="Y"){
                            $_GET[$sQuestionId]="-oth-";
                            $_GET[$sQuestionId."other"]=$completeValue;
                    }else{
                        Yii::log("Browser {$sBrowserCode} ({$completeValue} not found in answers",'warning','application.plugins.findUserAgentInfo');
                    }
                }
            }
        }
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
        if(empty($sessionUserAgent['Browser']))
        {
            $basedir=dirname(__FILE__); // this will give you the / directory
            Yii::setPathOfAlias('finduseragentinfo', $basedir);
            Yii::import('finduseragentinfo.Browser.lib.Browser');
            $browser=new Browser();
            $sessionUserAgent['Platform']=$browser->getPlatform();
            $sessionUserAgent['Browser']=$browser->getBrowser();
            $sessionUserAgent['Version']=$browser->getVersion();
            $sessionUserAgent['MajorVersion']=intval($browser->getVersion());
            /* Future developpement : fill a mobile/tablet YES/NO question */
            $sessionUserAgent['isMobileDevice']=$browser->isMobile();
            $sessionUserAgent['isTablet']=$browser->isTablet();
        }
        Yii::app()->session['UserAgentInfo']=$sessionUserAgent;
        return $sessionUserAgent;
    }

    /**
     * Translating
     * @var string
     * @return string
     */
    public function gT($string, $sEscapeMode = 'unescaped', $sLanguage = null)
    {
        if(Yii::app()->getConfig('versionnumber') >=3) {
            return parent::gT($string, $sEscapeMode, $sLanguage );
        }
        return gT($string,'unescaped', $sLanguage);
    }
}

