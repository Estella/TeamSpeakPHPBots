<?php

namespace com\tsphpbots\user;
use com\tsphpbots\db\DB;
use com\tsphpbots\utils\TestUtils;

/**
 * Generated by PHPUnit_SkeletonGenerator on 2016-06-28 at 15:40:10.
 */
class AuthTest extends \PHPUnit_Framework_TestCase {

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {

        $this->assertTrue(DB::connect(), "Database connection failed!");
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown() {
        
    }

    /**
     * @covers com\tsphpbots\user\Auth::isLoggedIn
     */
    public function testIsLoggedIn() {
        
        $res = Auth::isLoggedIn();
        $this->assertTrue(!is_null($res), "Unexpected auth result!");
    }

    /**
     * @covers com\tsphpbots\user\Auth::logIn
     */
    public function testLogIn() {

        $id = TestUtils::createUser("test log in", "testLogIn", "testLogIn");
        $this->assertTrue(!is_null($id), "Could not create user!");
 
        $pw = md5(md5("testLogIn") . htmlspecialchars(session_id()));

        $res = Auth::login("testLogIn", $pw);
        $this->assertTrue($res == true, "Could not login user!");        

        $this->assertTrue(Auth::isLoggedIn() == true, "Unexpected auth result!");
        
        // atempt a re-login with wrong data, it must result in a logout
        $res = Auth::login("testLogIn-WRONG", $pw);
        $this->assertTrue($res == false, "Could not login user!");        
        $this->assertTrue(Auth::isLoggedIn() == false, "Unexpected auth result!");
    }

    /**
     * @covers com\tsphpbots\user\Auth::logout
     */
    public function testLogout() {

        $id = TestUtils::createUser("test logout", "testLogout", "testLogout");
        $this->assertTrue(!is_null($id), "Could not create user!");
 
        $pw = md5(md5("testLogout") . htmlspecialchars(session_id()));

        $res = Auth::login("testLogout", $pw);
        $this->assertTrue($res == true, "Could not login user!");        

        $this->assertTrue(Auth::isLoggedIn() == true, "Unexpected auth result!");
        
        $res = Auth::logOut();
        $this->assertTrue($res == true, "Could not logout user!");        

        $this->assertTrue(Auth::isLoggedIn() == false, "Unexpected auth result!");
    }

    /**
     * @covers com\tsphpbots\user\Auth::getUserName
     */
    public function testGetUserName() {

        Auth::logout();
        $this->assertTrue(strlen(Auth::getUserName()) == 0, "Unexpected user name!");
        
        $id = TestUtils::createUser("test get user name", "testGetUserName", "testGetUserName");
        $this->assertTrue(!is_null($id), "Could not create user!");
 
        $pw = md5(md5("testGetUserName") . htmlspecialchars(session_id()));

        $res = Auth::login("testGetUserName", $pw);
        $this->assertTrue($res == true, "Could not login user!");        
        $this->assertTrue(strcmp(Auth::getUserName(), "testGetUserName") == 0, "Unexpected user name!");

        Auth::logout();
    }
}