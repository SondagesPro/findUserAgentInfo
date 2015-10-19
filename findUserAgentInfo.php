<?php
/**
 * findUserAgentInfo Plugin for LimeSurvey
 * Fill an answer with some user agent information
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2014 Denis Chenu <http://sondages.pro>
 * @copyright 2014 Validators <http://validators.nl>
 * @license GPL v3
 * @version 0.9.1
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
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
        'questioncodehelp' => array(
            'type' => 'info',
            'content' => '<div class="alert alert-info"><p><strong>The question(s) must be a short text question type or a single choice list (DropDown)</strong> (with optionnal other).</p><dl><dt>Short text question is filled with </dt><dd>Browser name - Browser major version</dd><dt>Single choice question try to find label filled with (in order):</dt><dd> <ol><li>Browser_name Browser_major_version</li><li>Browser_name</li></ol>If not found, it fill other text element (if exist)</dd><dt>Browser name</dt><dd>Only browser name are used here, try to find the browser name in the list of answer, put it in other if not found</dd><dt>Browser version (major version)</dt><dd>Browser major version, 0 to 50 can be a good idea</dd></dl><p><strong>Some example</strong></p><ul><li>firefox 19</li><li>ie 6</li><li>ie 11</li><li>opera 5</li><li>midori 0</li><li>chrome 40</li><li>chromium 28</li></ul></div>',
        ),
        'questioncodeexample' => array(
            'type' => 'info',
            'content' => '<div class="alert alert-info">You have an survey exemple file in the plugin directory (limesurvey_survey_browser.lss)</div>',
        ),
    );

    public function __construct(PluginManager $manager, $id) {
        parent::__construct($manager, $id);
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
                    'label'=>'Use findUserAgent infor plugin',
                    'options'=>array(
                        '0'=>gt("No"),
                        '1'=>gt("Yes"),
                    ),
                    'current' => $this->get('active', 'Survey', $event->get('survey'),1),
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
        if($oSurvey && $this->get('active', 'Survey', $iSurveyId,1))
        {
            $sessionUserAgent=$this->getSessionUserAgent();
            $sessionSurvey=Yii::app()->session["survey_{$iSurveyId}"];
            $iSrid=!empty($sessionSurvey['srid']) ? $sessionSurvey['srid'] : null;
            $aUpdatedValue=array();
            
            $aUpdatedValue=array_replace($aUpdatedValue,$this->getBrowserCode($iSrid,'browsercode'));
            $aUpdatedValue=array_replace($aUpdatedValue,$this->getBrowserCode($iSrid,'browsernamecode'));
            $aUpdatedValue=array_replace($aUpdatedValue,$this->getBrowserCode($iSrid,'browserversioncode'));

            Yii::app()->session["survey_{$iSurveyId}"]=$sessionSurvey;
        }
    }

    /**
    * get the array of browser code updated
    * 
    * return array
    */
    private function getBrowserCode($iSurveyId,$sBrowserCode)
    {
        
        $oQuestionBrowser=Question::model()->find("sid=:sid and title=:title and parent_qid=0",array(':sid'=>$iSurveyId,':title'=>$sBrowserCode));
        $sessionSurvey=Yii::app()->session["survey_{$iSurveyId}"];

        if($oQuestionBrowser && ($oQuestionBrowser->type=='S' or $oQuestionBrowser->type=='L'))
        {
            $sQuestionId=$oQuestionBrowser->sid."X".$oQuestionBrowser->gid."X".$oQuestionBrowser->qid;
            if(empty($sessionSurvey[$sQuestionId]))// Don't replace existing answer , AND do the same at each page.
            {
                if($oQuestionBrowser->type=='S'){
                    $sessionSurvey[$sQuestionId]=$sessionUserAgent['Browser']." ".$sessionUserAgent['MajorVersion'];
                }elseif($oQuestionBrowser->type=='L'){
                    $oAnswer=Answer::model()->find("qid=:qid and answer=:answer",array(':qid'=>$oQuestionBrowser->qid,':answer'=>$sessionUserAgent['Browser']." ".$sessionUserAgent['MajorVersion']));
                    if($oAnswer){
                        $sessionSurvey[$sQuestionId]=$oAnswer->code;
                    }else{
                        $oAnswer=Answer::model()->find("qid=:qid and answer=:answer",array(':qid'=>$oQuestionBrowser->qid,':answer'=>$sessionUserAgent['Browser']));
                        if($oAnswer){
                            $sessionSurvey[$sQuestionId]=$oAnswer->code;
                        }elseif($oQuestionBrowser->other=="Y"){
                            $sessionSurvey[$sQuestionId]="-oth-";
                            $sessionSurvey[$sQuestionId."other"]=$sessionUserAgent['Browser']." ".$sessionUserAgent['MajorVersion'];
                        }else{
                            tracevar("Unable to find the browser in question {$sBrowserCode}.");
                        }
                    }
                }
            }
            if(isset($sessionSurvey[$sQuestionId]))
                $this->saveUserAgent($sQuestionId,$sessionSurvey[$sQuestionId]);
            if(isset($sessionSurvey[$sQuestionId."other"]))
                $this->saveUserAgent($sQuestionId."other",$sessionSurvey[$sQuestionId."other"]);
        }
        return array();
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
            $sessionUserAgent['Platform']=strtolower($browser->getPlatform());
            $sessionUserAgent['Browser']=strtolower($browser->getBrowser());
            $sessionUserAgent['Version']=$browser->getVersion();
            $sessionUserAgent['MajorVersion']=intval($browser->getVersion());
            /* Future developpement : fill a mobile/tablet YES/NO question */
            $sessionUserAgent['isMobileDevice']=$browser->isMobile();
            $sessionUserAgent['isTablet']=$browser->isTablet();
        }
        Yii::app()->session['UserAgentInfo']=$sessionUserAgent;
        return $sessionUserAgent;
    }
    private function saveUserAgent($sColumn,$sValue)
    {
        $oEvent=$this->getEvent();
        $iSurveyId=$oEvent->get('surveyId');
        $bTableExist=in_array(App()->getDb()->tablePrefix."survey_{$iSurveyId}",App()->getDb()->getSchema()->getTableNames());
        if($bTableExist)
        {
            $sessionSurvey=Yii::app()->session["survey_{$iSurveyId}"];
            $iSrid=!empty($sessionSurvey['srid']) ? $sessionSurvey['srid'] : null;
            if($iSrid)
            {
                $savedUserAgent = Yii::app()->session['savedAgentInfo'];
                if(empty($savedUserAgent) || $savedUserAgent['srid']!=$iSrid)
                {
                    $savedUserAgent=array(
                        'srid'=>$iSrid,
                        'done'=>array(),
                        'saved'=>array(),
                    );
                }
                $aSavedArray=$savedUserAgent['saved'];
                $oSurvey=SurveyDynamic::model($iSurveyId)->find('id=:srid',array(":srid"=>$iSrid));
                if($oSurvey && !in_array($sColumn,$aSavedArray))
                {
                    $oSurvey->$sColumn=$sValue;
                    $oSurvey->save();
                    $aSavedArray[]=$sColumn;
                }
                $savedUserAgent['saved']=$aSavedArray;
                Yii::app()->session['savedAgentInfo']=$savedUserAgent;
            }
        }
    }
}

