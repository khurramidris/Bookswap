<?php
  /* Tests.php
   * This is a set of tests used on installation of the site to ensure a minimum of working settings.
   * If all settings are set correctly, it also ensures the database has been created.
  */
  /* Runs a series of tests to ensure the site is working properly */
  include('settings.php');
  // If the user doesn't have to install the site, go to index.php instead
  if(isInstalled()) {
    header('Location: index.php');
    return;
  }
  
  // If $_GET requires any replacements be made, do them, then start over
  if(!empty($_GET)) {
    performSettingsReplacements('settings.php', $_GET);
    header('Location: tests.php');
    return;
  }
  
  include_once(getIncludesWrapping('pdo'));
  $num_errors = 0;
  
  function error($num1, $num2, $str) {
    echo '<h2>Error ' . $num1 . '-' . $num2 . ': ' . $str . '</h2>' . PHP_EOL;
  }
  function details($str) {
    echo '<aside>' . $str . '</aside>' . PHP_EOL;
  }
  
  $tests = array(
    'Settings files' => array(
      'description' => 'Does settings.php exist, and is it readable?',
      'tests' => array(
        array(
          'function' => function() { return is_readable('settings.php'); },
          'error' => 'Could not open settings file.',
          'details' => 'The server could not read \'settings.php\'. Does it exist, and is it readable?'
        ),
        array(
          'function' => function() { return is_writable('settings.php'); },
          'error' => 'Could not write to settings file.',
          'details' => 'The server could not write to \'settings.php\'. This installation script needs to have write access to it, though you may return it to read-only when this is done.'
        ),
        array(
          'function' => function() { return is_dir(getIncludesPre()); },
          'error' => 'Could not find the ' . getIncludesPre() . ' directory.',
          'details' => 'The server could not find the ' . getIncludesPre() . ' directory of include files. Does it exist, and is it readable?'
        ),
        array(
          'function' => function() { return is_readable(getIncludesWrapping('pdo')); },
          'error' => 'Could not read the database connection include file. Does it exist, and is it readable?'
        )
      )
    ),
    'Location configurations' => array(
      'description' => 'Are the environment-specific settings correctly set?',
      'tests' => array(
        array(
          'function' => function() { return !!getBase(); },
          'error' => 'No base URL ("getBase()") provided for site.'
        ),
        array(
          'function' => function() { return !!getCDir(); },
          'error' => 'No directory ("getCDir()") provided for site.'
        ),
        array(
          'function' => function() { return substr(getBase(), -1) != '/'; },
          'error' => 'Your "getBase()" URL ends with a trailing slash. Please remove it.'
        ),
        array(
          'function' => function() { return substr(getCDir(), -1) != '/'; },
          'error' => 'Your "getCDir()" directory ends with a trailing slash. Please remove it.'
        ),
      )
    ),
    'Media files' => array(
      'description' => 'Do the CSS and JS directories exist, and are they readable?',
      'tests' => array(
        array(
          'function' => function() { return is_dir('CSS'); },
          'error' => 'Could not find the CSS directory.',
          'details' => 'The server could not find the CSS directory. Does it exist, and is it readable?'
        ),
        array(
          'function' => function() { return is_dir('JS'); },
          'error' => 'Could not find the JS directory.',
          'details' => 'The server could not find the JS directory. Does it exist, and is it readable?'
        ),
        array(
          'function' => function() { return is_readable('CSS/install.css'); },
          'error' => 'Could not open CSS/install.css.',
          'details' => 'The server could not find a sample CSS file at \'CSS/install.css\'. Does it exist, and is it readable?'
        ),
        array(
          'function' => function() { return is_readable('JS/install.js'); },
          'error' => 'Could not open JS/install.js.',
          'details' => 'The server could not find a sample JS file at \'JS/install.js\'. Does it exist, and is it readable?'
        )
      )
    ),
    'Database' => array(
      'description' => 'Can the database be accessed and used using the given credentials?',
      'tests' => array(
        array(
          'function' => function() { return getDBHost(); },
          'error' => 'You have a blank database host.',
          'details' => ''
        ),
        array(
          'function' => function() { return getDBUser(); },
          'error' => 'You have a blank database user.',
          'details' => ''
        )
      )
    ),
    'External libraries' => array(
      'tests' => array(
        array(
          'function' => function() { return function_exists('curl_version'); },
          'error' => 'You do not have cURL installed.',
          'details' => 'cURL is required to quickly access external webpages.'
        )
      )
    )
  );
  
  $test_group_num = 0;
  foreach($tests as $title=>$test_group) {
    $test_num = 0;
    if(!isset($test_group['tests'])) continue;
    foreach($test_group['tests'] as $test) {
      $status = $test['function']();
      if(!$status) {
        if(isset($test['error'])) error($test_group_num, $test_num, $test['error']);
        if(isset($test['details'])) details($test['details']);
        ++$num_errors;
      }
      ++$test_num;
    }
    ++$test_group_num;
  }
  
  if($num_errors) {
    echo '<h3>You have ' . $num_errors . ' error' . ($num_errors == 1 ? '' : 's') . ' in your installation. Please fix them, then try again.</h3>';
    return false;
  }
  
  /* Database installation script
   * 
   * Starts the system off with a new, blank set of database tables
   * 1. Create the database if it doesn't yet exist
   * 2. Create the `users` table
   * 3. Create the `books` table
  */
  try {
    $dbHost = getDBHost();
    $dbName = getDBName();
    $dbPass = getDBPass();
    $dbUser = getDBUser();
    $dbConn = getPDO($dbHost, '', $dbUser, $dbPass);
    
    // 1. Create the database if it doesn't yet exist
    $dbConn->prepare('
      CREATE DATABASE IF NOT EXISTS ' . $dbName
    )->execute();
    // From now on, everything will be in that database
    $dbConn->exec('USE ' . $dbName);
    
    // 2. Create the `users` table
    // * These are identified by the user_id int
    // (this should also be extended for Facebook integration)
    $dbConn->exec('
      CREATE TABLE IF NOT EXISTS `users` (
        `user_id` INT(10) NOT NULL AUTO_INCREMENT,
        `username` VARCHAR(127) NOT NULL,
        `password` VARCHAR(127) NOT NULL,
        `email` VARCHAR(127) NOT NULL,
        `salt` VARCHAR(127) NOT NULL,
        `role` INT(1),
        PRIMARY KEY (`user_id`)
      )
    ');
    
    // 3. Create the `books` table
    // This refers to the known information on a book
    // * These are identified by their 13-digit ISBN number
    // * Google Book IDs are also kept for book.php
    // * The authors list is split by endline characters
    $dbConn->exec('
      CREATE TABLE IF NOT EXISTS `books` (
        `isbn` VARCHAR(15) NOT NULL,
        `google_id` VARCHAR(127),
        `title` VARCHAR(127),
        `authors` VARCHAR(255),
        `description` TEXT,
        `publisher` VARCHAR(127),
        `year` VARCHAR(15),
        `pages` VARCHAR(7),
        PRIMARY KEY (`isbn`)
      )
    ');
    
    // 4. Create the `entries` table
    // This contains the entries of books by users
    // * These are also identified by their 13-digit ISBN number
    // * Prices are decimels, as dollars
    // * State may be one of the $bookStates
    // * Action may be one of the $bookActions
    // (this should use `isbn` and `user_id` as foreign keys)
    $dbConn->exec('
      CREATE TABLE IF NOT EXISTS `entries` (
        `entry_id` INT(10) NOT NULL AUTO_INCREMENT,
        `isbn` VARCHAR(15) NOT NULL,
        `user_id` INT(10) NOT NULL,
        `username` VARCHAR(127) NOT NULL,
        `bookname` VARCHAR(127) NOT NULL,
        `price` DECIMAL(19,4),
        `state` ENUM(' . makeSQLEnum(getBookStates()) . '),
        `action` ENUM(' . makeSQLEnum(getBookActions()) . '),
        `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`entry_id`)
      )
    ');
  }
  catch(Exception $err) {
    echo 'There was an error creating the database. Please try again.<br>' . PHP_EOL;
    print_r($err->getMessage());
    return false;
  }
  
  echo 'Ok!';
  performSettingsReplacements('settings.php', array('isInstalled' => true));
?>