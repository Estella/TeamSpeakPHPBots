<?php
/**
 * Copyright (c) 2016-2017 by Botorabi. All rights reserved.
 * https://github.com/botorabi/TeamSpeakPHPBots
 * 
 * License: MIT License (MIT), read the LICENSE text in
 *          main directory for more details.
 */

namespace com\tsphpbots\db;
use com\tsphpbots\db\DBConnection;
use com\tsphpbots\config\Config;
use com\tsphpbots\utils\Log;


/**
 * Database handler providing low-level functionality for accessing data.
 * The db account information must be provided in "config/Configuration.php".
 * 
 * @package   com\tsphpbots\db
 * @created   22th June 2016
 * @author    Botorabi
 */
class DB {

    /**
     * @var string Class tag for logging
     */
    protected static $TAG = "DB";

    /**
     * @var DBConnection Database connection handler
     */
    protected static $dbConnection = null;

    /**
     * Try to connect the database with the access data provided in "config/Configuration.php".
     * The connection will be persistent. If the connection is already established then just return true.
     * 
     * @return  true if the connection was successful, otherwise false
     */
    public static function connect() {
        if (!is_null(self::$dbConnection)) {
            return true;
        }

        self::$dbConnection = new DBConnection();
        $dbh = self::$dbConnection->connect(Config::getDB("host"), Config::getDB("hostPort"), Config::getDB("dbName"), Config::getDB("userName"), Config::getDB("password"));

        if (is_null($dbh)) {
            Log::error(self::$TAG, "Could not establish a connection to database!");
            self::$dbConnection = null;
            return false;
        }
        return true;
    }

    /**
     * Try to disconnect from database server, see the comments on DBConnection::disconnect.
     * 
     * @return       true if successful, otherwise false
     */
    public static function disconnect() {
        if (is_null(self::$dbConnection)) {
            return false;
        }
        $res = self::$dbConnection->disconnect();
        self::$dbConnection = null;
        return $res;
    }

    /**
     * Prepare a statement for the given SQL.
     * 
     * @param $sql      SLQ which is prepared
     * @return          Statement object
     * @throws          Exception or PDOException
     */
    public static function prepareStatement($sql) {
        return self::$dbConnection->prepareStatement($sql); 
    }

    /**
     * Execute a statement given its creation function and do an automatic connection recovery if it was lost.
     * The reason for passing a statement creator function instead of a statement is the automatic connection
     * loss recovery.
     * 
     * @param Function $fcnStatementCreator     A function creating a PDO statement
     * @return Object                           Return the PDO statement if successfull, otherwise null.
     */
    public static function executeStatement($fcnStatementCreator) {
        return self::$dbConnection->executeStatement($fcnStatementCreator);
    }

    /**
     * Return the ID used for the last table raw creation (so far its ID was an auto-generated).
     * 
     * @return  Last created ID used for a new table
     * @throws  Exception or PDOException
     */
    public static function getLastInsertId() {
        return self::$dbConnection->getLastInsertId();
    }

    /**
     * Return all found objects as field arrays. If the table could not be found
     * then null is returned.
     * 
     * @param $tableName    The name of database table
     * @param $filter       Optional array of field/value pair which is used as AND-filter.
     * @return              All found objects (can also be empty), or null if the table does not exist
     *                      or there is no connection to database.
     */
    public static function getObjects($tableName, array $filter = null) {
        
        if (!DB::connect()) {
            Log::warning(self::$TAG, "Cannot connect database, cannot get database objects!");
            return null;
        }

        $objects = [];
        try {
            $fulltablename = Config::getDB("dbName") . "." . $tableName;
            $fcnstmtcreator = function() use ($fulltablename, $filter) {
                if (!$filter) {
                    $statement = DB::prepareStatement("SELECT * FROM " . $fulltablename);
                }
                else {
                    $closure = "";
                    // define the query parameters
                    foreach($filter as $key => $value) {
                        if (strlen($closure) > 0) {
                            $closure .= " AND ";
                        }
                        $closure .= $key . " = " . ":" . $key;
                    }
                    $statement = DB::prepareStatement("SELECT * FROM " . $fulltablename . " WHERE " . $closure);
                    // bind the values
                    foreach($filter as $key => $value) {
                        $statement->bindValue(":" . $key, $value);
                    }
                }
                return $statement;
            };

            $stmt = self::executeStatement($fcnstmtcreator);
            if (is_null($stmt)) {
                Log::warning(self::$TAG, "getObjects, could not perform database operation!");
                return null;
            }

            $rawdata = $stmt->fetchAll();
            foreach($rawdata as $raw) {
                foreach($raw as $key => $value) {
                    if (!is_numeric($key)) {
                        $data[$key] = $value;
                    }
                }
                $objects[] = $data;
            }
        }
        catch (PDOException $e) {
            Log::warning(self::$TAG, "Problem occured while getting table raws, reason: " . $e->getMessage());
            return null;
        }
        return $objects;
    }

    /**
     * Return all found object IDs. If the table could not be found then null is returned.
     * 
     * @param $tableName    The name of database table
     * @return              All found object IDs (can also be empty), or null if the table does not exist
     *                      or there is no connection to database.
     */
    public static function getObjectIDs($tableName) {
        
        if (!DB::connect()) {
            Log::warning(self::$TAG, "Cannot connect database, cannot get database objects IDs!");
            return null;
        }

        $ids = [];
        try {
            $fulltablename = Config::getDB("dbName") . "." . $tableName;
            $fcnstmtcreator = function() use ($fulltablename) {
                return DB::prepareStatement("SELECT id FROM " . $fulltablename);
            };
            $stmt = self::executeStatement($fcnstmtcreator);
            if (is_null($stmt)) {
                Log::warning(self::$TAG, "getAllObjectIDs, could not perform database operation!");
                return null;
            }

            $rawdata = $stmt->fetchAll();
            foreach($rawdata as $raw) {
                $ids[] = $raw["id"];
            }
        }
        catch (PDOException $e) {
            Log::warning(self::$TAG, "Problem occured while getting table raws, reason: " . $e->getMessage());
            return null;
        }
        return $ids;
    }

    /**
     * Return count of all found objects. If the table could not be found then null is returned.
     * 
     * @param $tableName    The name of database table
     * @return              Count of found objects, or null if the table does not exist
     *                      or there is no connection to database.
     */
    public static function getObjectCount($tableName) {
        
        if (!DB::connect()) {
            Log::warning(self::$TAG, "Cannot connect database, cannot get database objects count!");
            return null;
        }

        try {
            $fulltablename = Config::getDB("dbName") . "." . $tableName;
            $fcnstmtcreator = function() use ($fulltablename) {
                return DB::prepareStatement("SELECT COUNT(*) FROM " . $fulltablename);
            };
            $stmt = self::executeStatement($fcnstmtcreator);
            if (is_null($stmt)) {
                Log::warning(self::$TAG, "getObjectCount, could not perform database operation!");
                return null;
            }

            $cnt = $stmt->fetchAll()[0][0];
        }
        catch (PDOException $e) {
            Log::warning(self::$TAG, "Problem occured while getting table raws, reason: " . $e->getMessage());
            return null;
        }
        return $cnt;
    }

    /**
     * Create a new object (table raw) and return its ID if successful.
     * 
     * @param $tableName        The name of database table
     * @param $fields           Raw fields, array of tuples [field name => value]
     *                          NOTE: If a value is an array then it gets converted to a
     *                                comma separated string array.
     * @return                  Object ID if successful, otherwise null.
     */
    public static function createObject($tableName, array $fields) {

        if (!DB::connect()) {
            Log::warning(self::$TAG, "Cannot connect database, cannot create database object!");
            return null;
        }

        try {
            $fulltablename = Config::getDB("dbName") . "." . $tableName;
            
            $fcnstmtcreator = function() use ($fulltablename, $fields) {            
                $sql = "INSERT INTO " . $fulltablename . "(";
                $params = "";
                $values = "";
                foreach($fields as $key => $value) {
                    if (strlen($params) > 0) {
                        $params .= ",";
                        $values .= ",";
                    }
                    $params .= $key;
                    $values .= ":" . $key;
                }
                $sql .= $params . ") VALUES(" . $values . ")";
                $statement = DB::prepareStatement($sql); 

                // bind the values
                foreach($fields as $key => $value) {
                    $statement->bindValue(":" . $key, self::encodeFieldValue($value));
                }
                return $statement;
            };

            $stmt = self::executeStatement($fcnstmtcreator);
            if (is_null($stmt)) {
                Log::warning(self::$TAG, "createObject, could not perform database operation!");
                return null;
            }

            $userid = DB::getLastInsertId();
        }
        catch (PDOException $e) {
            Log::warning(self::$TAG, "Problem occured while getting table raws, reason: " . $e->getMessage());
            return null;
        }
        return $userid;
    }

    /**
     * Store back the object changes to databank. Use $fields for updating only given
     * fields, otherwise all fields are updated.
     *
     * @param $tableName   The name of database table
     * @param $id          Object ID (table raw ID)
     * @param $fields      Field values which are updated, an array of 
     *                      tuples [field name -> value].
     * @return             true if successful, otherwise false
     */
    public static function updateObject($tableName, $id, array $fields) {

        if (!DB::connect()) {
            Log::warning(self::$TAG, "Cannot connect database, cannot update database object!");
            return null;
        }
 
        try {
            $fulltablename = Config::getDB("dbName") . "." . $tableName;
            $fcnstmtcreator = function() use ($fulltablename, $id, $fields) {
                $sql = "UPDATE " . $fulltablename . " SET ";
                $params = "";
                foreach($fields as $key => $value) {
                    if (strlen($params) > 0) {
                        $params .= ",";
                    }
                    $params .= $key . " = " . ":" . $key;
                }
                $sql .= $params . " WHERE id = " . $id;
                $statement = DB::prepareStatement($sql); 
                // bind the values
                foreach($fields as $key => $value) {
                    $statement->bindValue(":" . $key, self::encodeFieldValue($value));
                }
                return $statement;
            };
            $stmt = self::executeStatement($fcnstmtcreator);
            if (is_null($stmt)) {
                Log::warning(self::$TAG, "updateObject, could not perform database operation!");
                return false;
            }
        }
        catch (PDOException $e) {
            Log::warning(self::$TAG, "Problem occured while updating table raws, reason: " . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Delete the object with given ID from a database table.
     * 
     * @param $tableName    The name of database table
     * @param $id           Object ID
     * @return              true if successful, otherwise false.
     */
    public static function deleteObject($tableName, $id) {
        
        if (!DB::connect()) {
            Log::warning(self::$TAG, "Cannot connect database, cannot delete database object!");
            return false;
        }

        try {
            $fulltablename = Config::getDB("dbName") . "." . $tableName;
            $fcnstmtcreator = function() use ($fulltablename, $id) {
                $sql = "DELETE FROM " . $fulltablename . " WHERE id=" . $id;
                return DB::prepareStatement($sql);
            };
            $stmt = self::executeStatement($fcnstmtcreator);
            if (is_null($stmt)) {
                Log::warning(self::$TAG, "deleteObject, could not perform database operation!");
                return false;
            }
        }
        catch (PDOException $e) {
            Log::warning(self::$TAG, "Problem occured while deleting table raw, reason: " . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Encode a field value ready for storing into database.
     * If the value is an array then it will get converted to a 
     * comma separated value string, otherwise the original value is returned.
     *
     * @param $value    Field value
     * @return          Encoded field value
     */
    static protected function encodeFieldValue($value) {

        if (is_array($value) === true) {
            $valuestr = "";
            foreach($value as $v) {
                if (strlen($valuestr) > 0) {
                    $valuestr .= ",";
                }
                $valuestr .= $v;
            }
            $value = $valuestr;
        }
        return $value;
    }
}