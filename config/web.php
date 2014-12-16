<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
/**
 * web.php
 * This is the main configuration file for the DreamFactory Services Platform server application.
 */
use DreamFactory\Library\Utility\Includer;
use DreamFactory\Platform\Utility\Fabric;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Log;

//******************************************************************************
//* Variables
//******************************************************************************

/**
 * @type bool Indicates if this a DreamFactory Enterprise(tm) instance
 */
$_fabricHosted = false;
/**
 * @type string The generic pattern for the /rest route
 */
$_restPathPattern = 'rest/<path:[0-9a-zA-Z-_@&#!=,:;\/\^\$\.\|\{\}\[\]\(\)\*\+\? ]+>';

//******************************************************************************
//* Includes
//******************************************************************************

/**
 * Include the new DreamFactory constant provider object
 */
Includer::includeIfExists( __DIR__ . DIRECTORY_SEPARATOR . 'DreamFactory.php', true );

//  Include our global constants
Includer::includeIfExists( __DIR__ . '/constants.config.php', true );

/**
 * Load any environment variables first thing as they may be used by the database config
 */
DreamFactory::setEnvironmentVariables( $_envConfig );

/**
 * Load up the database configuration, free edition, private hosted, or others.
 * Look for non-default database config to override.
 */
if ( false === ( $_dbConfig = \DreamFactory::getConfigFile( 'database', true ) ) )
{
    if ( Fabric::fabricHosted() )
    {
        $_fabricHosted = true;
        $_dbConfig = Fabric::initialize();
    }
    else
    {
        /**
         * Database names vary by type of DSP:
         *
         *        1. Free Edition/Hosted:   DSP name
         *        2. Hosted Private:        hpp_<DSP Name>
         *        3. All others:            dreamfactory or whatever is in non-default config.
         */
        if ( false !== ( $_host = Fabric::hostedPrivatePlatform( true ) ) )
        {
            $_dbName =
                'hpp_' .
                str_ireplace( array('.dreamfactory.com', '-', '.cloud', '.'), array(null, '_', null, '_'), $_host );
        }
        else
        {
            $_dbName = 'dreamfactory';
        }

        //  Default config for local database
        $_dbConfig = array(
            'connectionString'      => 'mysql:host=localhost;port=3306;dbname=' . $_dbName,
            'username'              => 'dsp_user',
            'password'              => 'dsp_user',
            'emulatePrepare'        => true,
            'charset'               => 'utf8',
            'enableProfiling'       => defined( 'YII_DEBUG' ),
            'enableParamLogging'    => defined( 'YII_DEBUG' ),
            'schemaCachingDuration' => 3600,
        );
    }
}
/**
 * Load up the common configurations between the web and background apps,
 * setting globals whilst at it. REQUIRED file!
 */
$_commonConfig = \DreamFactory::getConfigFile( 'common', true, true );

//.........................................................................
//. The configuration
//.........................................................................

return array(
    /** Basics */
    'basePath'           => $_basePath . '/app',
    'name'               => $_appName,
    'runtimePath'        => $_logFilePath,
    'defaultController'  => $_defaultController,
    /** Service Handling: The default system resource namespaces */
    'resourceNamespaces' => array(
        'DreamFactory\\Platform\\Resources',
        'DreamFactory\\Platform\\Resources\\System',
        'DreamFactory\\Platform\\Resources\\User',
    ),
    /** Service Handling: The default system model namespaces */
    'modelNamespaces'    => array(
        'DreamFactory\\Platform\\Yii\\Models',
    ),
    /** CORS Configuration */
    'corsWhitelist'      => array(),
    'autoAddHeaders'     => true,
    'extendedHeaders'    => true,
    /** Pre-loaded components, all else are lazy-loaded */
    'preload'            => array('log', 'session', 'db'),
    /** Imports */
    'import'             => array(
        'system.utils.*',
        'application.models.*',
        'application.models.forms.*',
        'application.components.*',
    ),
    /** Modules */
    'modules'            => array(),
    /** Components */
    'components'         => array(
        //	Asset management
        'assetManager' => array(
            'class'      => 'CAssetManager',
            'basePath'   => $_docRoot . '/assets',
            'baseUrl'    => '/assets',
            'linkAssets' => true,
        ),
        //	Database configuration
        'db'           => array_merge( $_dbConfig, array('schemaCachingDuration' => 3600) ),
        //	Error management
        'errorHandler' => array(
            'errorAction' => $_defaultController . '/error',
        ),
        //	Route configuration
        'urlManager'   => array(
            'caseSensitive'  => false,
            'urlFormat'      => 'path',
            'showScriptName' => false,
            'rules'          => array(
                // REST patterns
                array('rest/get', 'pattern' => $_restPathPattern, 'verb' => 'GET'),
                array('rest/post', 'pattern' => $_restPathPattern, 'verb' => 'POST'),
                array('rest/put', 'pattern' => $_restPathPattern, 'verb' => 'PUT'),
                array('rest/patch', 'pattern' => $_restPathPattern, 'verb' => 'PATCH'),
                array('rest/merge', 'pattern' => $_restPathPattern, 'verb' => 'MERGE'),
                array('rest/delete', 'pattern' => $_restPathPattern, 'verb' => 'DELETE'),
                // Other controllers
                '<controller:\w+>/<id:\d+>'              => '<controller>/view',
                '<controller:\w+>/<action:\w+>/<id:\d+>' => '<controller>/<action>',
                '<controller:\w+>/<action:\w+>'          => '<controller>/<action>',
                //  Console controller's cache action has sub-commands
                array('console/cache/<command>', 'pattern' => 'console/cache/<command:[_0-9a-zA-Z-]+>'),
                // fall through to storage services for direct access
                array(
                    'storage/get',
                    'pattern' => '<service:[_0-9a-zA-Z-]+>/<path:[0-9a-zA-Z-_@&#!=,:;\/\^\$\.\|\{\}\[\]\(\)\*\+\? ]+>',
                    'verb'    => 'GET'
                ),
            ),
        ),
        'user'         => array(
            'allowAutoLogin' => true,
            'loginUrl'       => array($_defaultController . '/login'),
        ),
        'clientScript' => array(
            'scriptMap' => array(
                'jquery.js'     => false,
                'jquery.min.js' => false,
            ),
        ),
        //	Logging configuration
        'log'          => array(
            'class'  => 'CLogRouter',
            'routes' => array(
                array(
                    'class'       => 'DreamFactory\\Yii\\Logging\\LiveLogRoute',
                    'maxFileSize' => '102400',
                    'logFile'     => $_logFileName,
                    'logPath'     => $_logFilePath,
                    //  Super Debug Mode
                    //'levels' => 'error, warning, info, debug, trace, notice',
                    // Normal debug mode
                    'levels'      => 'error, warning, info, debug, notice',
                    // Production
                    //'levels'      => 'error, warning, notice',
                ),
            ),
        ),
        //	Database Cache
        'cache'        => $_dbCache,
    ),
    //.........................................................................
    //. Global application parameters
    //.........................................................................
    'params'             => $_commonConfig,
);
