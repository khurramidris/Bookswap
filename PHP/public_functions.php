<?php
    /**
     * BookSwap API Functions
     * 
     * <p>These are the functions listed in PHP/requests.php and callable via 
     * PHP/requests.php and api.php. Publically accessible functions have names
     * prepended with "public", while utilities do not.</p>
     * <p>Be careful dealing with input, as all arguments may be modified by
     * users: use sanitizations such as the ArgStrict and ArgLoose functions,
     * and PDO execution parameters.</p>
     * 
     * @package BookSwap
     */
  
    require_once('pdo.inc.php');
    require_once('sql.inc.php');
    require_once('db_actions.php');
    require_once('notifications.php');
  
    /* Helper functions to ensure argument safety
    */

    /**
     * Strict argument checking
     * 
     * Removes all characters from an argument string that aren't letters, 
     * numbers, or spaces. Calls ArgLoose on the argument first, to remove tags.
     * 
     * @param String $arg   The argument string to be cleaned
     * @return String
     */
    function ArgStrict($arg) {
        return preg_replace("/[^A-Za-z0-9 ]/", '', ArgLoose($arg));
    }
  
    /**
     * Loose argument checking
     * 
     * Calls PHP's strip_tags on the argument to remove HTML/XML and similar tags.
     * 
     * @param String $arg   The argument string to be cleaned
     * @return String
     */
    function ArgLoose($arg) {
        return strip_tags($arg);
    }
  
    /**
     * output({$settings}, "$message")
     * 
     * If $settings['verbose'] is truthy, this prints $message in a format 
     * specified by $settings['format']. In general, calls from regular BookSwap
     * PHP functions, and from requests.js, will not have verbose output, as 
     * they do not typically set verbose to true. Calls from api.php typically
     * have verbose set to true (see defaults.php::whatever) and format set to
     * json (see defaults.php::yuppers).
     * Options, with the functions used, are:
     * * "PHP": print_r(result) (default)
     * * "JSON": json_encode(result)
     * * "XML": xmlrpc_encode(result)
     * 
     * @param {Array} settings   An associative array of settings keys. Only
     *                           'verbose' and 'format' are used.
     * @param {Mixed} message   A message to be printed in some format, such as
     *                          a String, Number, or Array.
     */
    function output($settings, $message) {
        if(!isset($settings['verbose']) || !$settings['verbose']) {
            return;
        }

        if(!isset($settings['format'])) {
            $settings['format'] = getDefaultAPIFormat();
        }
        
        switch(strtolower($settings['format'])) {
            case 'xml':
                outputXML($message);
                return;
            case 'json':
                outputJSON($message);
                return;
        }
        
        // If no given format matched a provided format, use the PHP printer
        outputPHP($message);
    }
    
    /**
     * Standard output function for success outputs. This is a simple wrapper
     * around output() that outputs 'status' = 'success', with the message
     * within 'message'.
     * 
     * @param {Array} settings   The same associative array that's typically
     *                           passed into regular output() calls.
     * @param {Mixed} message   A message to be printed in any format.
     */
    function outputSuccess($settings, $message='Success') {
        output($settings, array(
            'status' => 'success',
            'message' => $message
        ));
        return true;
    }
    
    /**
     * Standard output function for failure outputs. This is a simple wrapper
     * around output() that outputs 'status' = 'failure', with the message
     * within 'message'.
     * 
     * @param {Array} settings   The same associative array that's typically
     *                           passed into regular output() calls.
     * @param {Mixed} message   A message to be printed in any format.
     */
    function outputFailure($settings, $message='Failure') {
        output($settings, array(
            'status' => 'failure',
            'message' => $message
        ));
        return false;
    }
    
    /**
     * Output a string as XML using xmlrpc_encode
     * 
     * @param Mixed $message
     */
    function outputXML($message) {
        echo xmlrpc_encode($message);
    }
    
    /**
     * Output a string as JSON using json_encode
     * 
     * @param Mixed $message
     */
    function outputJSON($message) {
        if(null !== JSON_PRETTY_PRINT) {
            echo json_encode($message, JSON_PRETTY_PRINT);
        } else {
            echo json_encode($message);
        }
    }
    
    /**
     * Output a string as PHP using echo or print_r
     * 
     * @param Mixed $message
     */
    function outputPHP($message) {
        if(is_array($message)) {
            print_r($message);
        } else {
            echo $message;
        }
    }
    
    /**
     * Allows keys in $arguments to be aliased by any of their allowed 
     * aliases in $key_aliases. 
     * For example, if 'password' is required but forms submit 'j_password',
     * use $aliases = array('password' => 'j_password').
     * 
     * @param {Array} arguments   The typical public function $arguments array.
     * @param {Array} aliases   An array of "key" => {alias} pairs, where keys
     *                          are the output keys in $arguments, and {alias}.
     *                          values are strings or arrays of allowed aliases.
     * @param {Boolean} override   Whether arguments that already exist may be
     *                             overriden by matched aliases.
     * 
     * @return Array   The parsed $arguments object (also passed by reference).
     */
    function allowArgumentAliases($arguments, $key_aliases, $override=false) {
        foreach($key_aliases as $argument => $aliases) {
            // If the alias is a string, check if it alone exists
            if(is_string($aliases)) {
                $arguments = allowArgumentAlias($arguments, $argument, $aliases, $override);
            }
            // If the alias is an array, check on each of its members
            else if(is_array($aliases)) {
                foreach($aliases as $alias) {
                    $arguments = allowArgumentAlias($arguments, $argument, $alias, $override);
                }
            }
        }
        return $arguments;
    }
    
    /**
     * A version of allowArgumentAliases that works on a single argument and 
     * optional alias. If the $arguments object contains the alias, and
     * (optionally) doesn't contain the argument, it is given the argument as
     * the value under that alias.
     * For example, if 'password' is required but forms submit 'j_password', 
     * use $argument='password' and $alias = 'j_password'.
     * 
     * @param {Array} arguments   The typical public function $arguments array.
     * @param {String} argument   The key from arguments that may need to be
     *                            filled by what's under the alias.
     * @param {String} alias   An optional alias to check under $arguments.
     * @param {Boolean} override   Whether arguments that already exist may be
     *                             overriden by matched aliases.
     * @return Array   The parsed $arguments object (also passed by reference).
     */
    function allowArgumentAlias($arguments, $argument, $alias, $override=false) {
        if(isset($arguments[$alias])) {
            if($override || !isset($arguments[$argument])) {
                $arguments[$argument] = $arguments[$alias];
            }
        }
        return $arguments;
    }
    
    /**
     * Checks to make sure each required argument is present in an associative
     * array. Any amount of strings or arrays of strings are allowed. If any
     * aren't present, it complains using output.
     * 
     * @param {Array} arguments   An array of arguments to be checked.
     * @param {Mixed} [requirements]   A string or array of strings that must be in
     *                             the arguments array.
     * @return Boolean   Whether each required argument was provided.
     */
    function requireArguments($arguments) {
        $num_args = func_num_args();
        
        // Starting with each of the required parts (not including $arguments)
        for($i = 1; $i < $num_args; $i += 1) {
            $requirement = func_get_arg($i);
            
            // If that argument's a string, check it directly
            if(is_string($requirement)) {
                if(!isset($arguments[$requirement])) {
                    // Lazy load an array of failure strings
                    if(!isset($failures)) {
                        $failures = [];
                    }
                    array_push($failures, $requirement);
                } 
            } 
            // If it's an array, check all the strings in it
            else {
                foreach($requirement as $key=>$value) {
                    if(!isset($arguments[$value])) {
                        // Lazy load an array of failure strings
                        if(!isset($failures)) {
                            $failures = [];
                        }
                        array_push($failures, $value);
                    }
                }
            }
        }
        
        // If any failures were detected, oh no!
        if(isset($failures)) {
            return outputFailure($arguments, array(
                'error' => 'Required arguments missing.',
                'failures' => $failures
            ));
        }
        
        return true;
    }
    
    /**
     * If the user isn't logged in, this complains gracefully using output().
     * 
     * @param {Array} arguments   An array of arguments to be checked.
     * @param {String} [action]   An optional short description of what the user
     *                            is trying to do, which will be printed out if 
     *                            the user isn't logged in.
     * @return Boolean   Whether the user is logged in.
     */
    function requireUserLogin($arguments, $action='do this') {
        if(!UserLoggedIn()) {
            outputFailure($arguments, 'You must be logged in to ' . $action . '.');
            return false;
        }
        return true;
    }
    
    /**
     * If the user isn't logged in and verified, this complains gracefully using
     * output().
     * 
     * @param {Array} arguments   An array of arguments to be checked.
     * @param {String} action   A short description of what the user is trying
     *                          to do, which will be printed out if the user
     *                          isn't verified.
     * @return Boolean   Whether the user is verified.
     */
    function requireUserVerification($arguments, $action='do this') {
        if(!requireUserLogin($arguments, $action)) {
            return false;
        }
        if(!UserVerified()) {
            return outputFailure($arguments, 'You must be verified to ' . $action . '.');
        }
        return true;
    }
    
    /**
     * If the user isn't logged in, verified, and an administrator, this 
     * complains gracefully using output().
     * 
     * 
     * @param {Array} arguments   An array of arguments to be checked.
     * @param {String} action   A short description of what the user is trying
     *                          to do, which will be printed out if the user
     *                          isn't an administrator.
     * @return Boolean   Whether the user is an administrator.
     */
    function requireUserAdministrator($arguments, $action='do this') {
        if(!requireUserVerification($arguments, $action)) {
            return false;
        }
        if(!UserAdministrator()) {
            return outputFailure($arguments, 'You must be an administrator to ' . $action . '.');
        }
        return true;
    }
    
    
    /* Public Functions
    */
    
    /**
     * Test
     * 
     * Quick testing function for use with api.php and public_functions.php. 
     * Displays the given arguments, the user's session, and a timestamp.
     */
    function publicTest($arguments) {
        $output = array(
            'arguments' => $arguments,
            'session' => $_SESSION,
            'timestamp' => time()
        );
        return outputSuccess($arguments, $output);
    }
    
    /**
     * UserGetInfo
     * 
     * Retrieves all publically available information for a user of the given
     * identity. Optionally, information on entries may be included, along with
     * notifications for your own account.
     * 
     * @param {String} identity   An identity to find the user by.
     * @param {String} [type]   The type of identity to find the user by (by 
     *                          default, "username")
     * @param {Boolean} [full]   Whether information on entries (and, for your
     *                           own lookups, notifications) should be included
     *                           (by default, false).
     */
    function publicUserGetInfo($arguments) {
        if(!requireArguments($arguments, 'identity')) {
            return false;
        }
        if(!requireUserLogin($arguments, 'get user information')) {
            return false;
        }
        
        $dbConn = getPDOQuick($arguments);
        $identity = ArgLoose($arguments['identity']);
        
        $type = isset($arguments['type']) ? ArgLoose($arguments['type']) : 'username';
        $full = isset($arguments['full']) ? boolify($arguments['full']) : false;
        
        $infoRaw = dbUsersGet($dbConn, $identity, $type);
        $user_id = $infoRaw['user_id'];
        $info = array();
        
        foreach($infoRaw as $key=>$value) {
            if(!is_numeric($key) && stripos($key, 'password') === false && $key != 'salt') {
                $info[$key] = $value;
            }
        }
        
        if(!$info) {
            return outputFailure($arguments, 'Could not find user.');
        }
        
        if($full) {
            $info['entries'] = dbEntriesGet($dbConn, $user_id);
            
            if($_SESSION['user_id'] == $user_id) {
                $info['notifications'] = dbNotificationsGet($dbConn, $user_id);
            }
        }
        
        return outputSuccess($arguments, $info);
    }
    
    /**
     * UserCreate
     * 
     * Creates a user of the given username, password, and email. This will call
     * dbUsersAdd on success, which sets the user's verification status to 
     * "Unverified" and sends them a verification email.
     * For ease of use with JavaScript handlers, all fields may also be taken in
     * with a 'j_' prefix (for example, 'j_username').
     * 
     * @param {String} username   The username of the new user.
     * @param {String} password   The password of the new user (must be secure:
     *                            >=1 uppercase letter, >=1 lowercase letter,
     *                            >=1 symbol, >=1 number, >=7 characters long).
     * @param {String} email   The email of the new user (must be a .edu email).
     */
    function publicUserCreate($arguments) {
        $fields = array('username', 'password', 'email');
        
        // Allow the 'j_*' versions of fields to exist if the regular ones don't
        foreach($fields as $field) {
            // If the field isn't set, that may be bad
            if(!isset($arguments[$field])) {
                // If the 'j_' prefix is there, swap that in
                if(isset($arguments['j_' . $field])) {
                    $arguments[$field] = $arguments['j_' . $field];
                }
            }
        }
        
        // Each field is required, now that any aliases are copied over
        if(!requireArguments($arguments, $fields)) {
            return false;
        }
        $username = $arguments['username'];
        $password = $arguments['password'];
        $email = $arguments['email'];
        
        // The password must be secure
        if(!isPasswordSecure($password)) {
            return outputFailure($arguments, 'The password isn\'t secure enough.');
        }
        
        // The email must be an academic email
        if(!isStringEmail($email)) {
            return outputFailure($arguments, 'That email isn\'t an actual email!');
        }
        if(!isEmailAcademic($email)) {
            return outputFailure($arguments, 'Sorry, right now we\'re only allowing school '
                . 'emails. Please use a .edu email address.');
        }
        
        // Also make sure that email isn't taken
        $dbConn = getPDOQuick($arguments);
        if(checkKeyExists($dbConn, 'users', 'email', $email)
          || checkKeyExists($dbConn, 'users', 'email_edu', $email)) {
            return outputFailure($arguments, 'The email \'' . $email . '\' is taken.');
        }

        // If successful, log the user in
        if(dbUsersAdd($dbConn, $username, $password, $email)) {
            publicUserLogin($arguments, true);
            return true;
        }
        
        return outputFailure($arguments);
    }
  
    /**
     * SendWelcomeEmail
     * 
     * Sends a welcome email to the current user that their account is active.
     */
    function publicUserSendWelcomeEmail($arguments) {
        require_once('templates.inc.php');
        
        $email = $_SESSION['email'];
        $username = $_SESSION['username'];
        $recipient = '<' . $username . '> ' . $email;
        $subject = 'Welcome to ' . getSiteName() . '!';
        
        $status = TemplateEmail($recipient, $subject, 'Emails/Welcome', array(
            'username' => $username
        ));
        
        if($status) {
            return outputSuccess($arguments);
        } else {
            return outputFailure($arguments, 'Couldn\'t send an email! Please try again.');
        }
    }
  
    /**
     * UserVerify
     * 
     * Attempts to verify the current user by checking a given verification code
     * against what's stored in the database. 
     * 
     * @param {Number} user_id   A given user_id, which must match the current
     *                           (logged-in) user's.
     * @param {String} code   A verification code from a sent email, to be 
     *                        matched against what's in the database.
     */
    function publicUserVerify($arguments) {
        if(!requireArguments($arguments, 'user_id', 'code')) {
            return false;
        }
        if(!requireUserLogin($arguments, 'verify yourself')) {
            return false;
        }
        
        $user_id = $arguments['user_id'];
        $code = $arguments['code'];

        // For security's sake, make sure the given user_id matches $_SESSION
        if($_SESSION['user_id'] != $user_id) {
            return outputFailure($arguments, 'The given user ID doesn\'t match the current user\'s');
        }

        // Get the corresponding user's code from the database
        $dbConn = getPDOQuick($arguments);
        $code_actual = getRowValue($dbConn, 'user_verifications', 'code', 'user_id', $user_id);

        // If it doesn't match, complain
        if($code != $code_actual) {
            return outputFailure($arguments, 'Invalid code provided! Please try again.');
        }

        // Otherwise, clear the verification and set the user role to normal
        setRowValue($dbConn, 'users', 'role', 'User', 'user_id', $user_id);
        $_SESSION['role'] = 'User';
        dbUserVerificationDeleteCode($dbConn, $user_id, true);

        return outputSuccess($arguments);
    }
    
    /**
     * SetVerificationEmail
     * 
     * Sets the current user's verification email and if directed, the password.
     * This is generally used by the page=verification, since new users must go
     * through that page.
     * If the email and password are the same as another user's, this will unify
     * the two accounts.
     * 
     * @param {String} email   An email from the user that must be a .edu.
     * @param {String} [password]   An optional password for the user.
     */
    function publicUserSetVerificationEmail($arguments) {
        if(!requireUserLogin($arguments, 'set your email and/or password')) {
            return false;
        }
        
        // Allow email and password arguments under the 'j_*' prefix
        if(!isset($arguments['email']) && isset($arguments['j_password'])) {
            $arguments['email'] = $arguments['j_email'];
        }
        if(!isset($arguments['password']) && isset($arguments['j_password'])) {
            $arguments['password'] = $arguments['j_password'];
        }
        
        if(!requireArguments($arguments, 'email', 'password')) {
            return false;
        }
        
        $email = $arguments['email'];
        $user_id = $_SESSION['user_id'];
        $username = $_SESSION['username'];

        // Only test password stuff if one is provided
        $has_pass = isset($arguments['password']) && $arguments['password'];
        if($has_pass) {
            $password = $arguments['password'];
        }

         // The password must be secure
        if($has_pass && !isPasswordSecure($password)) {
            return outputFailure($arguments, 'Your password isn\'t secure enough.');
        }

        // The email must be an academic email
        if(!isStringEmail($email)) {
            return outputFailure($arguments, 'That email isn\'t a real email, silly!');
        }
        if(!isEmailAcademic($email)) {
            return outputFailure($arguments, 'Sorry, right now we only allow school emails. Please use a .edu email address.');
        }

        // If that email is being used already, try to log into it 
        $dbConn = getPDOQuick($arguments);
        if(emailBeingUsed($dbConn, $email)) {
            // If it is, see if that password works, to unify the accounts
            if($has_pass && loginCheckPassword($dbConn, $email, $password)) {
                $edu_user_id = getRowValue($dbConn, 'users', 'user_id', 'email_edu', $email);
                $fb_user_id = $_SESSION['user_id'];
                $fb_id = $_SESSION['fb_id'];
                $fb_email = $_SESSION['email'];
                
                // Add the non-.edu email to the user as the primary email
                setRowValue($dbConn, 'users', 'email', $fb_email, 'user_id', $edu_user_id);
                
                // Delete the Facebook user, which deletes the facebook verification too
                $query = 'DELETE FROM `bookswap`.`users` WHERE `users`.`user_id` = :user_id';
                $stmnt = getPDOStatement($dbConn, $query);
                $stmnt->execute(array(':user_id' => $fb_user_id));
                
                // Add a new `facebookusers` row for the .edu user
                $query = '
                    INSERT INTO `bookswap`.`facebookusers` (
                        `fb_id`, `user_id`
                      ) 
                      VALUES (
                        :fb_id, :edu_user_id
                      )';
                $stmnt = getPDOStatement($dbConn, $query);
                $stmnt->execute(array('fb_id' => $fb_id,
                                      'edu_user_id' => $edu_user_id));
                
                // Log in with the new user
                loginAttempt($dbConn, $email, $password);
                return outputSuccess($arguments);
            }
            
           return outputFailure($arguments, 'That email is already being used.<br>'
                . '<small>If you have an account under ' . $email . ', '
                . 'you can log in here with your password to unify the two '
                . 'accounts.</small>');
        }

        // If a password is given, check that for validity too
        if($has_pass) {
            // The password must be secure
            if(!isPasswordSecure($password)) {
                return outputFailure($arguments, 'Your password isn\'t secure enough.');
            }
            
            // Since the password is secure, give it to the user
            $salt = hash('sha256', uniqid(mt_rand(), true));
            $salted = hash('sha256', $salt . $password);
            $query = '
              UPDATE `users` 
              SET `password` = :password, 
                  `salt` = :salt
              WHERE  `user_id` = :user_id
            ';
            $stmnt = getPDOStatement($dbConn, $query);
            $stmnt->execute(array(':password' => $salted,
                                  ':salt'     => $salt,
                                  ':user_id'  => $user_id));
        }

        // All is is good, give the user the email_edu and code
        setRowValue($dbConn, 'users', 'email_edu', $email, 'user_id', $user_id);
        dbUserVerificationAddCode($dbConn, $user_id, $username, $email);

        // Give the user's session the user information
        copyUserToSession(getUserInfo($dbConn, $user_id));

        outputSuccess($arguments);
    }
    
    /**
     * UserRequestPasswordReset
     * 
     * Adds a code in the `password_resets` table that indicates a user should
     * be able to reset their (likely forgotten) password using that code.
     * 
     * @param {String} email   Either of the user's email addresses.
     * @param {String} username   The user's username, for security's sake.
     */
    function publicUserRequestPasswordReset($arguments) {
        $arguments = allowArgumentAliases($arguments, array(
            // 'username' => 'j_username',
            'email' => 'j_email'
        ));
        
        if(!requireArguments($arguments, 'email'/*, 'username'*/)) {
            return false;
        }
        
        $dbConn = getPDOQuick($arguments);
        $email = $arguments['email'];
        // $username = $arguments['username'];
        
        // Grab user info from the database, and ensure it matches
        $user_info = getUserFromEmail($dbConn, $email);
        if(empty($user_info)/* || $username != $user_info['username']*/) {
            return outputFailure($arguments, 'Incorrect user information.');
        }
        $user_id = $user_info['user_id'];
        $username = $user_info['username'];
        
        // With the request verified, create a reset code and send them an email
        $status = dbUserPasswordResetAddCode($dbConn, $user_id, $username, $email);
        if($status) {
            return outputSuccess($arguments);
        } else {
            return outputFailure($arguments, 'An unknown failure occurred... :(');
        }
    }
    
    /**
     * UserPerformPasswordReset
     * 
     * Given a password reset code created by RequestPasswordReset, this 
     * will attempt to set a given password value to the database.
     * 
     * @param {String} email   Either of the user's email addresses.
     * @param {String} username   The user's username, for security's sake.
     * @param {String} code   The password reset code from `password_resets`.
     * @param {String} value   The new value for the password.
     */
    function publicUserPerformPasswordReset($arguments) {
        $arguments = allowArgumentAliases($arguments, array(
            'email' => 'j_email',
            // 'username' => 'j_username',
            'value' => ['j_value', 'password', 'j_password'],
            'code' => 'j_code'
        ));
        if(!requireArguments($arguments, 'code', 'email',/* 'username',*/ 'value')) {
            return false;
        }
        
        $dbConn = getPDOQuick($arguments);
        $code = $arguments['code'];
        $email = $arguments['email'];
        // $username = $arguments['username'];
        $password = $arguments['value'];
        
        // The password must be secure
        if(!isPasswordSecure($password)) {
            return outputFailure($arguments, 'The password isn\'t secure enough.');
        }
        
        // Grab user info from the database, and ensure it matches
        $user_info = getUserFromEmail($dbConn, $email);
        if(empty($user_info)/* || $username != $user_info['username']*/) {
            return outputFailure($arguments, 'Incorrect user information.');
        }
        $user_id = $user_info['user_id'];
        
        // Grab reset info from the database, and ensure it exists
        $reset_info = dbUserPasswordResetGetCode($dbConn, $user_id);
        if(empty($reset_info) || !isset($reset_info['code'])) {
            return outputFailure($arguments, 'An unknown failure occurred... :(');
        }
        
        // Make sure the user's code matches what's in the database.
        if($reset_info['code'] != $code) {
            return outputFailure($arguments, 'An unknown failure occurred... :(');
        }
        
        // Perform the replacement, and delete the old code
        $status = dbUsersEditPassword($dbConn, $user_id, $password);
        dbUserPasswordResetDeleteCode($dbConn, $user_id);
        if($status) {
            return outputSuccess($arguments);
        } else {
            return outputFailure($arguments, 'An unknown failure occurred... :(');
        }
    }
    
    /**
     * UserLogin
     * 
     * Attempts to log in with the given credentials. This is a small function
     * that acts as a pipe to <c>loginAttempt("email", "password")</c>.
     * 
     * @param {String} email   An email to log in with.
     * @param {Password} password   The password for the user account.
     */
    function publicUserLogin($arguments) {
        if(!requireArguments($arguments, 'email', 'password')) {
            return false;
        }
        
        $dbConn = getPDOQuick($arguments);
        $email = $arguments['email'];
        $password = $arguments['password'];
        if(loginAttempt($dbConn, $email, $password)) {
            return outputSuccess($arguments);
        } else {
            return outputFailure($arguments);
        }
    }
  
    /**
     * FacebookLogin
     * 
     * Attempts to log in via Facebook with the given credentials. This is a
     * small function that acts as a pipe to
     * <c>facebookLoginAttempt("fb_id")</c>.
     * 
     * @param {String} email   An email to log in with.
     * @param {String} fb_id   The Facebook user's ID (on Facebook).
     */
    function publicUserLoginFacebook($arguments) {
        if(!requireArguments($arguments, 'email', 'fb_id')) {
            return false;
        }
        
        $dbConn = getPDOQuick($arguments);
        $email = $arguments['email'];
        $username = $arguments['name'];
        $fb_id = $arguments['fb_id'];

        // Attempt to log in with Facebook normally
        $user_info = facebookLoginAttempt($dbConn, $fb_id);
        
        // If it didn't work, try to add the user, then log in again
        if(!$user_info){
            dbFacebookUsersAdd($dbConn, $username, $fb_id, $email);
            $user_info = facebookLoginAttempt($dbConn, $fb_id);
        }

        // If it did, congratulate the user
        if($user_info) {
            return outputSuccess($arguments);
        }
        // If it didn't work (couldn't login or register), complain
        else {
            return outputFailure($arguments);
        }
    }
    
    /**
     * EditUsername
     * 
     * Edits the current user's username. The new value must be different from 
     * the old and longer than one character.
     * 
     * @param {String} value   The requested new username.
     */
    function publicUserEditUsername($arguments) {
        if(!requireUserLogin($arguments, 'edit your username')) {
            return false;
        }
        if(!requireArguments($arguments, 'value')) {
            return false;
        }
        
        $user_id = $_SESSION['user_id'];
        $username_old = $_SESSION['username'];
        $username_new = ArgLoose($arguments['value']);
        
        // Don't do anything if it's an invalid value
        if(!$username_new || strlen($username_new) <= 1) {
            return outputFailure($arguments, 'Invalid username given... :(');
        }
        
        // Don't do anything if it's the same as before
        if($username_new == $username_old) {
            return outputFailure($arguments, 'Same username as before... :(');
        }
        
        // Replace the user's actual username
        $dbConn = getPDOQuick($arguments);
        dbUsersRename($dbConn, $user_id, $username_new, 'user_id');
        
        // Reset the $_SESSION username to be that of the database's
        $_SESSION['username'] = getRowValue($dbConn, 'users', 'username', 'user_id', $user_id);
    }
  
    /**
     * EditEmail
     * 
     * Edits the current user's primary email address. The new value must be a
     * valid email address, and not already used in the database.
     * 
     * @param {String} value   The requested new primary email.
     */
    function publicUserEditEmail($arguments) {
        if(!requireUserLogin($arguments, 'edit your email')) {
            return false;
        }
        if(!requireArguments($arguments, 'value')) {
            return false;
        }
        
        $user_id = $_SESSION['user_id'];
        $email_old = $_SESSION['email'];
        $email_new = ArgLoose($arguments['value']);
        
        // Don't do anything if it's an invalid value
        if(!$email_new || !filter_var($email_new, FILTER_VALIDATE_EMAIL)) {
            return outputFailure($arguments, 'Invalid email given... :(');
        }
        
        // Don't do anything if it's the same as before
        if($email_new == $email_old) {
            return outputFailure($arguments, 'Same email as before... :(');
        }
        
        $dbConn = getPDOQuick($arguments);
        
        // Don't do anything if the email is taken
        if(checkKeyExists($dbConn, 'users', 'email', $email_new)
                || checkKeyExists($dbConn, 'users', 'email_edu', $email_new)) {
            return outputFailure($arguments, 'The email \'' . $email_new . '\' is already taken :(');
        }
        
        // Replace the user's actual email
        dbUsersEditEmail($dbConn, $user_id, $email_new, 'user_id');
        
        // Reset the $_SESSION email to be that of the database's
        $_SESSION['email'] = getRowValue($dbConn, 'users', 'email', 'user_id', $user_id);
        
        return outputSuccess($arguments);
    }
  
    /**
     * EditEmailEdu
     * 
     * Edits the current user's educational email address. The new value must be
     * a valid email address, not already used in the database, and end with a
     * '.edu'.
     * 
     * @param {String} value   The requested new educational email.
     */
    function publicUserEditEmailEdu($arguments) {
        if(!requireUserLogin($arguments, 'edit your email')) {
            return false;
        }
        if(!requireArguments($arguments, 'value')) {
            return false;
        }
        
        $user_id = $_SESSION['user_id'];
        $email_old = $_SESSION['email_edu'];
        $email_new = ArgLoose($arguments['value']);
        
        // Don't do anything if it's an invalid value
        if(!$email_new || !filter_var($email_new, FILTER_VALIDATE_EMAIL)) {
            return outputFailure($arguments, 'Invalid email given... :(');
        }
        
        // Don't do anything if it's the same as before
        if($email_new == $email_old) {
            return outputFailure($arguments, 'Same email as before... :(');
        }
        
        // Don't do anything if it's not an .edu email
        if(!endsWith($email_new, '.edu')) {
            return outputFailure($arguments, 'Not an .edu address... :(');
        }
        
        $dbConn = getPDOQuick($arguments);
        
        // Don't do anything if the email is taken
        if(checkKeyExists($dbConn, 'users', 'email', $email_new)
                || checkKeyExists($dbConn, 'users', 'email_edu', $email_new)) {
            return outputFailure($arguments, 'The email \'' . $email_new . '\' is already taken :(');
        }
        
        dbUsersEditEmailEdu($dbConn, $user_id, $email_new, 'user_id');
        
        // Reset the $_SESSION email to be that of the database's
        $_SESSION['email_edu'] = getRowValue($dbConn, 'users', 'email_edu', 'user_id', $user_id);
        
        return outputSuccess($arguments);
    }
    
    /**
     * UserEditPassword
     * 
     * Edits the current user's password. The new value must be secure: >=1 
     * uppercase letter, >=1 lowercase letter, >=1 symbol, >=1 number, 
     * >=7 characters long.
     * 
     * @param {String} value   The requested new password.
     */
    function publicUserEditPassword($arguments) {
        if(!requireUserLogin($arguments, 'edit your password')) {
            return false;
        }
        if(!requireArguments($arguments, 'value')) {
            return false;
        }
        
        $user_id = $_SESSION['user_id'];
        $password = $arguments['value'];
        
        // The password must be secure
        if(!isPasswordSecure($password)) {
            return outputFailure($arguments, 'The password isn\'t secure enough.');
        }
        
        // Replace the user's actual password, and make a new salt
        $dbConn = getPDOQuick($arguments);
        dbUsersEditPassword($dbConn, $user_id, $password, 'user_id');
        
        // Reset the user's session, including password and salt
        copyUserToSession(getUserInfo($dbConn, $user_id));
        
        return outputSuccess();
    }
    
    /**
     * UserEditDescription
     * 
     * Edits the current user's description. The new value is stripped of tags
     * and must be shorter than 140 characters.
     * 
     * @param {String} value   The requested new description.
     */
    function publicUserEditDescription($arguments) {
        if(!requireUserLogin($arguments, 'edit your description')) {
            return false;
        }
        if(!requireArguments($arguments, 'value')) {
            return false;
        }
        
        $user_id = $_SESSION['user_id'];
        $description = ArgLoose($arguments['value']);
        $dbConn = getPDOQuick($arguments);
        
        // Attempt to edit the description in the database
        $status = dbUsersEditDescription($dbConn, $user_id, $description);
        
        // If it was successful, also edit the user's session
        if($status) {
            $_SESSION['description'] = $description;
            return outputSuccess($arguments, getUserInfo($dbConn, $user_id));
        } else {
            return outputFailure($arguments, getUserInfo($dbConn, $user_id));
        }
        
    }
    
    /**
     * Search
     * 
     * Runs a search on the local database based on a given query value. The 
     * results will be weighted on column importance (Title > Description, etc.)
     * when possible.
     * Results will be printed as HTML based on Templates/Books/<format>, where
     * <format> is an optional argument that defaults to "Medium".
     * The weighted search uses weights from defaults.php::getSearchWeights().
     * 
     * @param {String} value   A query term to search the database for.
     * @param {String} [format]   What format to print the items in (defaults to
     *                            "Medium").
     * @param {Number} [offset]   A starting offset for the results (defaults to
     *                            0).
     * @param {Number} [limit]   A maximum number of results to display
     *                           (defaults to 7).
     * @param {Number} [total]   The number of results found. This normally
     *                           isn't passed in by the user, but rather has a 
     *                           separate query to find the number. However,
     *                           enabling total as an argument enables it to be 
     *                           cached for successive searches.
     * @todo Allow format=JSON, format=XML, etc.
     * @todo Don't hardcode the HTML echo calls (use a "Books" template)
     * @todo Use 'size' instead of 'format'.
     */
    function publicSearch($arguments) {
        if(!requireArguments($arguments, 'value')) {
            return false;
        }
        
        $dbConn = getPDOQuick($arguments);
        $value_raw = ArgLoose($arguments['value']);
        $value = str_replace(' ', '%', $value_raw);
        $format = isset($arguments['format']) ? ArgStrict($arguments['format']) : 'Medium';
       
        // The user may give a different column to search on
        if(isset($arguments['column'])) {
            $column = strtolower(ArgStrict($arguments['column']));
        } else {
            $column = 'title';
        }
     
        // Same with the limit per page
        if(isset($arguments['limit']) && $arguments['limit'] != 'Limit...') {
            $limit = (int) ArgStrict($arguments['limit']);
        } else {
            $limit = 7;
        }
       
        // The offset is determined per page
        if(isset($arguments['offset'])) {
            $offset = (int) ArgStrict($arguments['offset']);
        } else {
            $offset = 0;
        }

        // The total is the total number of results, cached for future use
        if(isset($arguments['total']) && $arguments['total'] != 0) {
            $total = (int) ArgStrict($arguments['total']);
        }
        // Only query for total if a new search is made!
        else {
            // If searching on everything, query all columns for the value
            if($column == 'all') {
                $total_query = '
                    SELECT COUNT(*) FROM `books`
                    WHERE
                         `title`       LIKE :value_percent
                      OR `authors`     LIKE :value_percent
                      OR `description` LIKE :value_percent
                      OR `publisher`   LIKE :value_percent
                      OR `year`        LIKE :value_percent
                      OR `isbn`        LIKE :value_percent ;
               ';
            }
            // If searching on one column, you need only search that one
            else {
                $total_query = '
                    SELECT COUNT(*) FROM `books`
                    WHERE `' . $column . '` LIKE :value_percent ;
               ';
            }
            
            // Run the query and retrieve the total as the count
            $_stmnt = getPDOStatement($dbConn, $total_query);
            $_stmnt->execute(array(':value_percent' => '%' . $value . '%'));
            $result = $_stmnt->fetchAll(PDO::FETCH_ASSOC);
            $total = $result[0]['COUNT(*)'];
        }

        $weights = getSearchWeights();
     
        // Prepare the search query for individual column
        if($column != 'all') {
            $WEIGHT = $weights[$column];
            /*
              Select all results from table `books` that match this column.
              Weight is defined in defaults.php.
              Results are ordered by weight, descending:
                - Perfect match: Full weight
                - Beginning of string: 2/3 of weight
                - Exists in string: 1/3 of weight
              Searches are performed as follows:
                - Select all from `books` where:
                  - If the value is a perfect match, return it with its full weight
                  - Else:
                    - If the beginning of the string matches, return it with 2/3 weight
                    - Else:
                      - If the value exists in the string, return it with 1/3 weight
                      - Else:
                        - Return 0
                - Order by weight
                - Limit and offset returned results
            */
            $query = '
              SELECT *, 
                IF(`' . $column . '` LIKE :value_strictest, ' . $WEIGHT . ', 
                  IF(`' . $column . '` LIKE :value_stricter, ' . ($WEIGHT * 2) / 3 . ', 
                    IF(`' . $column . '` LIKE :value_percent, ' . $WEIGHT / 3 . ', 0)
                  )
                )
                AS `weight`
              FROM `books`
              WHERE (
                    `' . $column . '` LIKE :value_strictest
                OR  `' . $column . '` LIKE :value_stricter
                OR  `' . $column . '` LIKE :value_percent
              )
              ORDER BY `weight` DESC
              LIMIT ' . $limit . ' OFFSET ' . $offset . ';
            ';
        }
        // Prepare the search query for the entire table
        else {
            $TITLE_WEIGHT = $weights['title'];
            $AUTHORS_WEIGHT = $weights['authors'];
            $DESC_WEIGHT = $weights['description'];
            $PUB_WEIGHT = $weights['publisher'];
            $YEAR_WEIGHT = $weights['year'];
            $ISBN_WEIGHT = $weights['isbn'];
            
            /*
              Select all results from table `books` that match all columns.
              Search procedure is the same as for individual columns.
              ISBN and year must match exactly to be returned.
            */
            $query = '
              SELECT *,
                  IF(`title` LIKE :value_strictest, ' . $TITLE_WEIGHT . ', 
                    IF(`title` LIKE :value_stricter, ' . ($TITLE_WEIGHT*2)/3 . ', 
                      IF(`title` LIKE :value_percent, ' . $TITLE_WEIGHT/3 . ', 0)
                    )
                  )
                + IF(`authors` LIKE :value_strictest, ' . $AUTHORS_WEIGHT . ', 
                    IF(`authors` LIKE :value_stricter, ' . ($AUTHORS_WEIGHT*2)/3 . ', 
                      IF(`authors` LIKE :value_percent, ' . $AUTHORS_WEIGHT/3 . ', 0)
                    )
                  )
                + IF(`description` LIKE :value_strictest, ' . $DESC_WEIGHT . ', 
                    IF(`description` LIKE :value_stricter, ' . ($DESC_WEIGHT*2)/3 . ', 
                      IF(`description` LIKE :value_percent, ' . $DESC_WEIGHT/3 . ', 0)
                    )
                  )
                + IF(`publisher` LIKE :value_strictest, ' . $PUB_WEIGHT . ', 
                    IF(`publisher` LIKE :value_stricter, ' . ($PUB_WEIGHT*2)/3 . ', 
                      IF(`publisher` LIKE :value_percent, ' . $PUB_WEIGHT/3 . ', 0)
                    )
                  )
                + IF(`year` LIKE :value_strictest, ' . $YEAR_WEIGHT . ', 0)
                + IF(`isbn` LIKE :value_strictest, ' . $ISBN_WEIGHT . ', 0)
                AS `weight`
              FROM `books`
              WHERE (
                    `title`         LIKE :value_strictest
                OR  `title`         LIKE :value_stricter
                OR  `title`         LIKE :value_percent
                OR  `authors`       LIKE :value_strictest
                OR  `authors`       LIKE :value_stricter
                OR  `authors`       LIKE :value_percent
                OR  `description`   LIKE :value_strictest
                OR  `description`   LIKE :value_stricter
                OR  `description`   LIKE :value_percent
                OR  `publisher`     LIKE :value_strictest
                OR  `publisher`     LIKE :value_stricter
                OR  `publisher`     LIKE :value_percent
                OR  `year`          LIKE :value_strictest
                OR  `isbn`          LIKE :value_strictest
              )
              ORDER BY `weight` DESC
              LIMIT ' . $limit . ' OFFSET ' . $offset . ';
            ';
        }

        // Run the query
        $stmnt = getPDOStatement($dbConn, $query);
        $stmnt->execute(array(
            ':value_strictest' => $value,
            ':value_stricter'  => $value . '%',
            ':value_percent'   => '%' . $value . '%'
        ));
        
        // Fetch the results, and print them all in the desired format
        $results = $stmnt->fetchAll(PDO::FETCH_ASSOC);
        $num_shown = 0;
        foreach($results as $result) {
            $result['is_search'] = true;
            TemplatePrint('Books/' . $format, 0, array_merge($result, array(
                'from_search' => true
            )));
            $num_shown += 1;
        }

        // A summary is also included
        echo '<div class="search_end book" style="text-align:center">search on ';
        echo getLinkHTML('search', $value_raw, array('value' => $value_raw));

        // Print number of results shown, and number of first result
        echo ': ' . $num_shown . ' results ' . ($results ? 'shown' . ($offset ? ' (starting from result #' . ($offset + 1) . ')' : '') . ($total > $limit + $offset ? '; ' . $total . ' found' : '') : 'found') . '.';
     
        // If 5 or less results are returned, link to import page
        if($total < 5 && $column != 'isbn') {
            echo '<div class="message" style="text-align:center">';
            echo '  Looks like there aren\'t many results...';
            echo ' Try <a href="index.php?page=import&import=' . $value_raw;
            echo '">importing</a>';
            echo ' more results from Google Books.';
            echo '</div>' . PHP_EOL;
        }

        // Show "Previous" and "Next" buttons for loading results
        $prev_offset = (int)$offset - (int)$limit;
        $next_offset = (int)$offset + (int)$limit;
        echo '<div class="message" style="text-align:center">';
        if(0 >= (int)$limit - (int)$offset) {
            echo '<a href="index.php?page=search&value=' . $value . '&column=' . $column . '&limit=' . $limit . '&offset=' . $prev_offset . '&total=' . $total . '">&lt; Previous</a>';
        }
        if(0 >= (int)$limit - (int)$offset && $total > (int)$offset + (int)$limit) {
            echo ' &bull; ';
        }
        if($total > (int)$offset + (int)$limit) {
            echo '<a href="index.php?page=search&value=' . $value . '&column=' . $column . '&limit=' . $limit . '&offset=' . $next_offset . '&total=' . $total . '">Next &gt;</a>';
        }
        echo '</div>';

        /* 
          UP NEXT:
           - Direct searches for ISBN to book page, else return no results
           - Direct to import page at bottom?
           - Implement better ISBN and year search
           - Something else I can't remember right now
        */
    }
    
    /**
     * BookGetInfo
     * 
     * Retrieves a simple table of information for a book of a given ISBN.
     * 
     * @param {String} isbn   An ISBN number (as a string, in case it starts
     *                        with 0) of a book to be imported.
     */
    function publicBookGetInfo($arguments) {
        if(!requireArguments($arguments, 'isbn')) {
            return false;
        }
        
        $dbConn = getPDOQuick($arguments);
        $isbn = ArgStrict($arguments['isbn']);
        
        $info = dbBooksGet($dbConn, $isbn);
        
        if(!$info) {
            return outputFailure($arguments, 'ISBN not found');
        } else {
            return outputSuccess($arguments, $info);
        }
    }
    
    /**
     * BookGetEntries
     * 
     * Retrieves all entries for a book of a given ISBN. An action ("Buy" or 
     * "Sell") may be given optionally.
     * 
     * @todo Allow for other formats, such as HTML or XML
     * @param {String} isbn   An ISBN number (as a string, in case it starts
     *                        with 0) of a book to be imported.
     * @param {String} [action]   An action to filter the queries on (if not
     *                            given, all entries on that ISBN are returned).
     */
    function publicBookGetEntries($arguments) {
        if(!requireArguments($arguments, 'isbn', 'action')) {
            return false;
        }
        
        $dbConn = getPDOQuick($arguments);
        $isbn = ArgStrict($arguments['isbn']);
        
        // If an action isn't given, default to '%' (anything)
        if(!isset($arguments['action']) || !$arguments['action']) {
            $action = '%';
        } else {
            $action = $arguments['action'];
        }
        
        // Prepare the initial query
        $query = '
            SELECT * FROM `entries`
            WHERE `isbn` LIKE :isbn
            AND `action` LIKE :action
        ';
        
        // Run the query
        $stmnt = getPDOStatement($dbConn, $query);
        $durp = $stmnt->execute(array(':isbn' => $isbn,
                                      ':action' => $action));
        
        // Return a JSON encoding of the results
        $result = $stmnt->fetchAll(PDO::FETCH_ASSOC);
        outputSuccess($arguments, $result);
        return $result;
    }
    
    /**
     * BookImport
     * 
     * Quick handler to go to publicBookImportFull or publicBookImportISBN, 
     * depending on the search type. Works very well for JavaScript handling.
     * 
     * @param {String} type   What type of import to attempt: 'full' redirects 
     *                        to publicBookImportFull, while all others go to 
     *                        publicBookImportISBN. Keep in mind those functions
     *                        have their own argument requirements as well.
     */
    function publicBookImport($arguments) {
        if(!requireUserVerification($arguments, 'import a book')) {
            return false;
        }
        if(!requireArguments($arguments, 'type')) {
            return false;
        }
        
        if(ArgStrict($arguments['type']) == 'full') {
            return publicBookImportFull($arguments);
        } else {
            return publicBookImportISBN($arguments);
        }
    }
    
    /**
     * BookImportISBN
     * 
     * Imports a book by querying a given ISBN. If that ISBN isn't already in 
     * the database, this queries the Google Books API on that ISBN and calls 
     * the internal bookProcessObject on the given JSON result.
     * https://developers.google.com/books/docs/v1/using
     * https://www.googleapis.com/books/v1/volumes?q=isbn:9780073523323&key=AIzaSyD2FxaIBhdLTA7J6K5ktG4URdCFmQZOCUw
     * 
     * @todo Format the results instead of hardcoding raw HTML (template them?)
     * @param {String} isbn   An ISBN number (as a string, in case it starts
     *                        with 0) of a book to be imported.
     */
    function publicBookImportISBN($arguments) {
        if(!requireUserVerification($arguments, 'import a book')) {
            return false;
        }
        if(!requireArguments($arguments, 'isbn')) {
            return false;
        }
        
        $isbn = ArgStrict($arguments['isbn']);
        
        // Make sure the ISBN is valid
        if(!(strlen($isbn) == 10 || strlen($isbn) == 13) || !is_numeric($isbn)) {
            return outputFailure($arguments, 'Invalid ISBN given.');
        }
        
        // Make sure the ISBN doesn't already exist
        $dbConn = getPDOQuick($arguments);
        if(checkKeyExists($dbConn, 'books', 'isbn', $isbn)) {
            return outputFailure($arguments, 'ISBN ' . $isbn . ' already exists!');
        }
        
        // Get the actual JSON contents and decode them
        $url = 'https://www.googleapis.com/books/v1/volumes?'
             . 'q=isbn:' . $isbn . '&key=' . getGoogleKey();
        $result = json_decode(getHTTPPage($url));
        
        // If there was an error, oh no!
        if(isset($result->error)) {
            return outputFailure($arguments, $result->error->message);
        }
        
        // Attempt to get the first item in the list (which will be the book)
        if(!isset($result->items) || !isset($result->items[0])) {
            return outputFailure($arguments, 'No results for ' . $isbn);
        }
        $book = $result->items[0];
        
        // Call the backend bookProcessObject to add the book to the database
        require_once('imports.inc.php');
        $arguments['dbConn'] = $dbConn;
        $arguments['book'] = $book;
        $added = bookProcessObject($arguments);
        
        // If that was successful, hooray!
        if($added) {
            echo '<aside class="success">ISBN ' . $isbn . ' was added to our database as ';
            echo getLinkHTML('book', getRowValue($dbConn, 'books', 'title', 'isbn', $isbn), array('isbn'=>$isbn));
            echo '</aside>';
        }
        // Otherwise nope
        else {
            echo '<aside class="failure">ISBN ' . $isbn . ' returned no results.</aside>';
        }
        
        return outputSuccess();
    }
  
    /**
     * BookImportFull
     * 
     * Sends a request query to the Google Books API with the given search term.
     * If results are received that aren't already in the database, they are
     * added.
     * 
     * @todo Format the results instead of hardcoding raw HTML (template them?)
     * @param {String} value   A query term to search in the Google Books API.
     */
    function publicBookImportFull($arguments) {
        if(!requireUserVerification($arguments, 'import a book')) {
            return false;
        }
        if(!requireArguments($arguments, 'value')) {
            return false;
        }
        
        $value = urlencode($arguments['value']);
        $query = 'https://www.googleapis.com/books/v1/volumes?';
        $query .= 'q=' . $value;
        $query .= '&key=' . getGoogleKey();
        
        // Run the query and get the results
        $arguments['data'] = getHTTPPage($query);
        $arguments['term'] = $value;
        
        // Use the private function to add the JSON book to the database
        require_once('imports.inc.php');
        $results = bookImportFromJSON($arguments);
        return outputSuccess($arguments, $results);
    }
  
    /**
     * BookCreateManual
     * 
     * Creates a book manually with the given arguments, rather than querying 
     * information from the Google Books API. Because this could be somewhat
     * insecure, only administrators are allowed to do it.
     * 
     * @param {String} isbn 
     * @param {Number} googleID 
     * @param {String} title 
     * @param {String} authors 
     * @param {String} description 
     * @param {String} publisher 
     * @param {Number} year 
     * @param {Number} pages 
     */
    function publicBookCreateManual($arguments) {
        if(!requireUserAdministrator($arguments, 'create a book')) {
            return false;
        }
        if(!requireArguments($arguments, 'isbn', 'googleID', 'title', 'authors',
                'description', 'publisher', 'year', 'pages')) {
            return false;
        }
        
        $isbn = $arguments['isbn'];
        $good = dbBooksAdd(getPDOQuick($arguments), 
            $isbn, $arguments['googleID'], $arguments['title'],
            $arguments['authors'], $arguments['description'],  
            $arguments['publisher'], $arguments['year'], $arguments['pages']);
        
        
        if($good) {
            return outputSuccess($arguments, 'ISBN ' . $isbn . ' added successfully!');
        }
    }
    
    /**
     * EntryAdd
     * 
     * Adds an entry on a book for the current user. The user  must be logged in
     * and verified.
     *
     * @param {String} isbn   The ISBN number (as a String, in case it starts
     *                        with 0) of the book.
     * @param {String} action   The entry action to be listed (either "buy" or
     *                          "sell").
     * @param {String} price   The price the user wants to {action} the book
     *                         for (as a String, for 0s).
     * @param {String} state   The requested state for the book to be in,
     *                         which must be one of the predeclared strings in
     *                         defaults.php::getBookStates().
     */
    function publicEntryAdd($arguments) {
        if(!requireUserVerification($arguments, 'add an entry')) {
            return false;
        }
        
        if(!requireArguments($arguments, 'isbn', 'action', 'price', 'state')) {
            return false;
        }
        
        $username = $_SESSION['username'];
        $user_id = $_SESSION['user_id'];
        $dbConn = getPDOQuick($arguments);

        // Fetch the necessary arguments
        $isbn = ArgStrict($arguments['isbn']);
        $action = ArgStrict($arguments['action']);
        $price = ArgLoose($arguments['price']);
        $state = ArgStrict($arguments['state']);

        // Send the query
        $entry_id = dbEntriesAdd($dbConn, $isbn, $user_id, $action, $price, $state);
        if($entry_id !== false) {
            sendAllEntryNotifications($dbConn, $entry_id);
            return outputSuccess($arguments, 'Entry added successfully!');
        } else {
            return outputFailure($arguments, 'Could not add entry. Please try again!');
        }
    }
  
    /**
     * EntryEdit
     * 
     * Edits the price and/or state of the current user's entry for a particular 
     * book, given the entry_id. 
     *
     * @param {Number} entry_id   The ID of the entry from the database.
     * @param {String} action   The entry action to filter on (one of "buy" or
     *                          "sell").
     * @param {String} price   The price the user wants to {action} the book for
     *                         (as a String, for 0s).
     * @param {String} [state]   The requested new state for the book to be in,
     *                           which must be one of the predeclared strings in
     *                           defaults.php::getBookStates().
     */
    function publicEntryEdit($arguments) {
        if(!requireUserVerification($arguments, 'edit an entry price')) {
            return false;
        }
        
        if(!requireArguments($arguments, 'entry_id')) {
            return false;
        }

        // Fetch the necessary arguments
        $entry_id = ArgStrict($arguments['entry_id']);
        $user_id = $_SESSION['user_id'];
        $dbConn = getPDOQuick($arguments);
        
        // Ensure the entry is the user's
        if(getRowValue($dbConn, 'entries', 'user_id', 'entry_id', $entry_id) != $user_id) {
            return outputFailure($arguments, 'Could not edit entry of entry_id ' . $entry_id);
        }
        
        // Collect which edits to make
        $doEditPrice = isset($arguments['price']);
        $doEditState = isset($arguments['state']);
        if(!$doEditPrice && !$doEditState) {
            return outputFailure($arguments, 'Neither price nor state edits were requested.');
        }
        
        // Collect the edit values
        if($doEditPrice) {
            $price = ArgLoose($arguments['price']);
        }
        if($doEditState) {
            $state = ArgStrict($arguments['state']);
        }
        
        // If the state is given, make sure it's one of the allowed values
        if($doEditState && array_search($state, getBookStates()) === false) {
            return outputFailure($arguments, $state . ' is not allowed as a book state.');
        }
        
        // Attempt the one or two required edits
        if($doEditPrice) {
            $statusPrice = dbEntriesEditPrice($dbConn, $entry_id, $price);
        } else {
            $statusPrice = true;
        }
        if($doEditState) {
            $statusState = dbEntriesEditState($dbConn, $entry_id, $state);
        } else {
            $statusState = true;
        }
        
        if($statusPrice && $statusState) {
            return outputSuccess($arguments);
        } else {
            $failures = '';
            if($statusState) {
                $failures .= 'edit state';
            }
            if(!$statusPrice) {
                $failures .= 'edit price';
            }
            return outputFailure($arguments, 'Could not ' . implode(' or ', $failures) . '.');
        }
    }
  
    /**
     * EntryDelete
     * 
     * Removes an entry regarding a particular book for the current user, if it
     * already exists.
     * 
     * @param {Number} entry_id   The ID of the entry from the database.
     */
    function publicEntryDelete($arguments) {
        if(!requireUserVerification($arguments, 'delete an entry')) {
            return false;
        }
        if(!requireArguments($arguments, 'entry_id')) {
            return false;
        }
        
        $username = $_SESSION['username'];
        $user_id = $_SESSION['user_id'];
        $entry_id = ArgStrict($arguments['entry_id']);
        $dbConn = getPDOQuick($arguments);

        // Send the query and print the results
        if(dbEntriesRemove($dbConn, $entry_id)) {
            return outputSuccess($arguments, 'Entry removed successfully!');
        } else {
            return outputFailure($arguments, 'Entry removal failed. Try again?');
        }
    }
  
    /**
     * GetNumNotifications
     * 
     * Gets the number of notifications the current user has, or -1 if anonymous.
     * 
     * @return {Number}
     */
    function publicGetNumNotifications($arguments=[]) {
        if(!requireUserVerification($arguments, 'get your notifications')) {
            return -1;
        }
        
        $dbConn = getPDOQuick($arguments);
        return dbNotificationsCount($dbConn, $_SESSION['user_id']);
    }
  
    /**
     * DeleteNotification
     * 
     * Deletes a notification of a given ID, if it belongs to the current user.
     * 
     * @param {Number} notification_id   The ID of the notification to delete.
     */
    function publicDeleteNotification($arguments) {
        if(!requireUserVerification($arguments, 'delete a notification')) {
            return false;
        }
        if(!requireArguments($arguments, 'notification_id')) {
            return false;
        }
        
        $dbConn = getPDOQuick($arguments);
        dbNotificationsRemove($dbConn, $arguments['notification_id']);
    }
    
    /**
     * PrintRecentListings
     * 
     * Prints the most recent entries on the site, in chronological order. Calls
     * TemplatePrint("Entry") on PHP/db_actions::dbEntriesGetRecent().
     * 
     * @param String [identifier]   An optional key by which to filter entries,
     *                              such as "isbn" or "user_id_a"
     * @param String [value]    A value (required only if "identifier" is given)
     *                          to filter for, such as "9780073523323" for 
     *                          "isbn".
     */
    function publicPrintRecentListings($arguments) {
        // Check if there's an identifier
        if(isset($arguments['identifier'])) {
            $identifier = $arguments['identifier'];
            $value = $arguments['value'];
        }
        else {
            $identifier = $value = false;
        }

        // Get each of the recent entries
        $dbConn = getPDOQuick($arguments);
        $entries = dbEntriesGetRecent($dbConn, $identifier, $value);

        // If there are any, for each of those entries, print them out
        if(count($entries)) {
            foreach($entries as $entry) {
                TemplatePrint('Entries/Medium', 0, $entry);
            }
        } else {
            outputSuccess($arguments, 'Nothing going!');
        }
    }
    
    /**
     * PrintUserBooks
     * 
     * Prints the formatted displays of all books referenced by the entries of
     * a user.
     * 
     * @param {Number} user_id   The unique numeric ID of the user.
     * @param {String} size   The Book size to print the book listings, in
     *                        ("small", "medium", or "large").
     * @param {String} action   The entry action to filter on ("buy" or "sell").
     */
    function publicPrintUserBooks($arguments) {
        if(!requireArguments($arguments, 'user_id', 'size', 'action')) {
            return false;
        }
        
        $user_id = ArgStrict($arguments['user_id']);
        $size = ArgStrict($arguments['size']);
        $action = ArgStrict($arguments['action']);
        $dbConn = getPDOQuick($arguments);
        
        // Get each of the ISBNs of that type
        $isbns = dbEntriesGetISBNs($dbConn, $user_id, $action);
        
        // If there were none, stop immediately
        if(!$isbns) {
            return outputSuccess($arguments, 'Nothing going!');
        }
        
        // For each one, query the book information, and print it out
        foreach($isbns as $key=>$entry) {
            $entry = array_merge($entry, array(
                'action' => $action,
                'user_id' => $user_id
            ));
            $book = dbBooksGet($dbConn, $entry['isbn']);
            TemplatePrint('Books/' . $size, 0, array_merge($entry, $book));
        }
        return true;
    }
    
    /**
     * PrintBookEntries
     * 
     * Prints all the entries that a particular user (user_id) has on a single
     * book (isbn).
     * 
     * @todo Allow for other formats, such as HTML or XML
     * @param {Number} user_id   The unique numeric ID of the user.
     * @param {String} isbn   An ISBN number (as a string, in case it starts
     *                        with 0) of a book to be imported.
     * @param {String} action   The entry action to be listed (either "buy" or
     *                          "sell").
     * @param {String} size   The Book size to print the book listings in, from
     *                        ("small" or "medium").
     * @param 
     */
    function publicPrintBookEntries($arguments) {
        if(!requireArguments($arguments, 'user_id', 'isbn', 'action', 'size')) {
            return false;
        }
        
        $user_id = ArgStrict($arguments['user_id']);
        $isbn = ArgStrict($arguments['isbn']);
        $action = ArgStrict($arguments['action']);
        $size = ArgStrict($arguments['size']);
        $dbConn = getPDOQuick($arguments);
        
        $entries = dbBookEntriesGet($dbConn, $isbn, $user_id, $action);
        
        if($entries === false) {
            return outputFailure($arguments, 'Could not fetch entries for ' . $isbn);
        }
        
        // If there were none, stop immediately
        if(!$entries) {
            return outputSuccess($arguments, 'Nothing going!');
        }
        
        // For each one, query the book information, and print it out
        echo '<table class="entry-table">';
        foreach($entries as $key=>$entry) {
            $result = dbBooksGet($dbConn, $entry['isbn']);
            TemplatePrint('Entries/' . $size, 0, array_merge($result, $entry));
        }
        echo '</table>';
        return true;
    }
    
    /**
     * PrintRecommendationsDatabase
     * 
     * Given a user_id, this prints out all entries from other users who have
     * listings on any of the same books as the user's.
     *
     * @param {Number} user_id   The unique numeric ID of the user.
     */
    function publicPrintRecommendationsDatabase($arguments) {
        if(!requireArguments($arguments, 'user_id')) {
            return false;
        }
        
        $dbConn = getPDOQuick($arguments);
        $user_id = ArgStrict($arguments['user_id']);

        // Prepare the query
        // http://stackoverflow.com/questions/5505244/selecting-matching-mutual-records-in-mysql/5505280#5505280
        // http://stackoverflow.com/questions/16490120/select-from-same-table-where-two-columns-match-and-third-doesnt
        $query = '
          SELECT * FROM (
            SELECT DISTINCT a.* FROM
            `entries` a
            # matching rows in entries against themselves
            INNER JOIN `entries` b
            # not from the given user; ISBNs are the same, but users and actions are not
            ON  a.user_id <> :user_id
            AND a.isbn = b.isbn
            AND b.user_id = :user_id
            AND a.action <> b.action
            AND a.entry_id <> b.entry_id
          ) AS matchingEntries
          # while the above alias is not used, MySQL requires all derived tables to
          # have an alias
          NATURAL JOIN `books` NATURAL JOIN `users`
        ';

        // Run the query
        $stmnt = getPDOStatement($dbConn, $query);
        $stmnt->execute(array(':user_id' => $user_id));

        // Get the results to print them out
        $results = $stmnt->fetchAll(PDO::FETCH_ASSOC);
        if(empty($results)) {
            return outputSuccess($arguments, 'Nothing going!');
        } 
        else {
            foreach($results as $result) {
                TemplatePrint("Entries/Medium", 0, $result);
            }
        }
    }
  
    /**
     * PrintRecommendationsUser
     * 
     * Given a user's user_id (a) and another user's user_id (b), this finds and
     * prints all matching entries between the two users.
     *
     * @param {Number} user_id_a   The unique numeric ID of the first user.
     * @param {Number} user_id_b   The unique numeric ID of the second user.
    */
    function publicPrintRecommendationsUser($arguments) {
        if(!requireArguments($arguments, 'user_id_a', 'user_id_b')) {
            return false;
        }
        
        $dbConn = getPDOQuick($arguments);
        $user_id_a = ArgStrict($arguments['user_id_a']);
        $user_id_b = ArgStrict($arguments['user_id_b']);

        // Prepare the query
        // http://stackoverflow.com/questions/5505244/selecting-matching-mutual-records-in-mysql/5505280#5505280
        $query = '
          SELECT * FROM (
            SELECT a.*
            FROM `entries` a
            # matching rows in entries against themselves
            INNER JOIN `entries` b
            # where ISBNs are the same, and the two user_ids match
            ON a.isbn = b.isbn
            AND a.user_id LIKE :user_id_a
            AND b.user_id LIKE :user_id_b
          ) AS matchingEntries
          # while the above alias is not used, MySQL requires all derived tables to
          # have an alias
          NATURAL JOIN `books` NATURAL JOIN `users`
        ';

        // Run the query
        $stmnt = getPDOStatement($dbConn, $query);
        $stmnt->execute(array(':user_id_a' => $user_id_a,
                              ':user_id_b' => $user_id_b));

        // Get the results to print them out
        $results = $stmnt->fetchAll(PDO::FETCH_ASSOC);
        if(empty($results)) {
            outputSuccess($arguments, 'Nothing going!');
        } 
        else {
            foreach($results as $result) {
                TemplatePrint("Entries/Medium", 0, $result);
            }
        }
    }
  
    /**
     * PrintNotifications
     * 
     * Finds and prints all notifications of the current user.
     */
    function publicPrintNotifications($arguments=[]) {
        if(!requireUserVerification($arguments, 'print notifications')) {
            return false;
        }
        
        $dbConn = getPDOQuick($arguments);
        $result = dbNotificationsGet($dbConn, $_SESSION['user_id']);
        if(empty($result)) {
            outputSuccess($arguments, 'Nothing going!');
        } else {
            foreach($result as $notification) {
                TemplatePrint('Notification', 0, $notification);
            }
        }
    }
?>
