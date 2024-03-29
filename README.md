findUserAgentInfo
==================

A plugin for LimeSurvey fo fill some questions with browser information

See the plugin in action on [findUserAgentInfo example survey](https://demo.sondages.pro/772229)

## Installation

See [Install and activate a plugin for LimeSurvey](http://extensions.sondages.pro/install-and-activate-a-plugin-for-limesurvey.html)

### Via GIT
- Go to your LimeSurvey Directory (version up to 3.0 only)
- Clone in plugins/findUserAgentInfo directory

### Via ZIP dowload
- Download <http://dl.sondages.pro/findUserAgentInfo.zip>
- Extract : `unzip findUserAgentInfo.zip`
- Move the directory to plugins/ directory inside LimeSUrvey

## Usage

The plugin use one question for each browser information, this question can be a short text question or a single choice question (drop down). 
The plugin use question title (not attribute) for browser information, you can choose the code at global settings, and activate the plugin by default.

With a short text question : you get the value set by Browser.php in the text directly. For boolean value : you get Y or N.

With a drop down question : if the value is set by answer text : this value is selected. If value are not found and question allow other : the value is set as other.

For mobile, tablet and robot : value are Y or N, in answer code (by default) or in answer text.

For version number : the value selected is the maximum near your current value. For example : if you have 1 to 10 value and use Firefox 93 : 10 is selected.

For browser and platform : you find 2 TSV file in docs from Browser 1.9.6. The answer text are set in [Browser.php](https://gitlab.com/SondagesPro/QuestionSettingsType/findUserAgentInfo/-/blob/master/Browser/Browser.php#L56).

## Home page & Copyright
- HomePage <http://extension.sondages.pro/>
- Copyright © 2014-2023 Denis Chenu <http://sondages.pro>
- Copyright © 2014 Validators <http://validators.nl>
- Browser.php is © Chris Schuld (http://chrisschuld.com/), distributed under [MIT](https://github.com/cbschuld/Browser.php/blob/master/LICENSE.md)

Distributed under [AFFERO GNU GENERAL PUBLIC LICENSE Version 3](http://www.gnu.org/licenses/agpl.txt) licence.
If you need a more permissive Licence [contact](http://extensions.sondages.pro/about/contact.html).
