<?php

namespace FeaturedFlagsTest;

use PHPUnit_Extensions_Database_TestCase as TestCase;
use FeaturedFlags\FeaturedFlagsImpl;
use FeaturedFlags\FeaturedFlagsModel;
use Redis;

class FeaturedFlagsTest extends TestCase
{
    static protected $pdo;
    static protected $conn;
    /** @var FeaturedFlagsImpl $_featuredFlags */
    protected $_featuredFlags;
    /** @var  Redis $_redis */
    protected $_redis;

    public function setUp()
    {
        parent::setUp();
        $this->_redis = $this->getMockBuilder('Redis')
            ->disableOriginalConstructor()
            ->setMethods(array('get','set'))
            ->getMock();
        $this->_redis->method('get')->willReturn(false);

        $date = "2016-01-21 01:02:03";
        $this->_featuredFlags = new FeaturedFlagsImpl(self::$pdo, $this->_redis, $date);
    }

    public function getConnection()
    {
        if (self::$conn === null) {
            if (self::$pdo == null) {
                self::$pdo = new \PDO('sqlite:/tmp/featuredflags.db', '', '',
                    array());
            }
            self::$conn = $this->createDefaultDBConnection(self::$pdo, "featuredflags");
            $schemaFile = dirname(__FILE__) . '/../fixtures/schema.sql';
            self::$conn->getConnection()->exec(file_get_contents($schemaFile));
        }

        return self::$conn;
    }

    protected function getDataSet()
    {
        $fixtures = dirname(__FILE__) . '/../fixtures';
        return $this->createMySQLXMLDataSet("$fixtures/00-data.xml");
    }

    public function testGetInstance()
    {
        $featuredFlags = FeaturedFlagsImpl::getInstance(self::$pdo, $this->_redis);
        $this->assertInstanceOf('FeaturedFlags\FeaturedFlags', $featuredFlags);
    }

    public function testGetInstanceWithountRedis()
    {
        $featuredFlags = FeaturedFlagsImpl::getInstance(self::$pdo);
        $this->assertInstanceOf('FeaturedFlags\FeaturedFlags', $featuredFlags);
    }

    public function testIsEnabledNotExistReturnFalse()
    {
        $flag = 'flag_notExist';
        $response = $this->_featuredFlags->isEnabled($flag);
        $this->assertFalse($response);
    }

    public function testIsEnabledExistReturnFalse()
    {
        $flag = 'flag1';
        $response = $this->_featuredFlags->isEnabled($flag);
        $this->assertFalse($response);
    }

    public function testIsEnabledReturnTrue()
    {
        $flag = 'flag2';
        $response = $this->_featuredFlags->isEnabled($flag);
        $this->assertTrue($response);
    }

    public function testIsEnabledWithOneParamNotExistReturnFalse()
    {
        $flag = 'flag2';
        $params = array(
            'data' => 'dt_not_exist'
        );
        $response = $this->_featuredFlags->isEnabled($flag, $params);
        $this->assertFalse($response);
    }

    public function testIsEnabledWithOneParamReturnFalse()
    {
        $flag = 'flag2';
        $params = array(
            'data' => 'dt_false'
        );
        $response = $this->_featuredFlags->isEnabled($flag, $params);
        $this->assertFalse($response);
    }

    public function testIsEnabledWithOneParamReturnTrue()
    {
        $flag = 'flag2';
        $params = array(
            'data' => 'dt_true'
        );
        $response = $this->_featuredFlags->isEnabled($flag, $params);
        $this->assertTrue($response);
    }

    public function testIsEnabledWithTwoParamNotExist()
    {
        $flag = 'flag2';
        $params = array(
            'data' => 'dt_true',
            'data2' => 'not_exist'
        );
        $response = $this->_featuredFlags->isEnabled($flag, $params);
        $this->assertFalse($response);
    }

    public function testIsEnabledWithTwoParamExist()
    {
        $flag = 'flag2';
        $params = array(
            'data' => 'dt_true',
            'data2' => 'exist'
        );
        $response = $this->_featuredFlags->isEnabled($flag, $params);
        $this->assertTrue($response);
    }

    public function testIsEnabledWithDateReturnTrue()
    {
        //date is "2016-01-21 01:02:03"
        $flag = 'flagDateJanuary';
        $response = $this->_featuredFlags->isEnabled($flag);
        $this->assertTrue($response);
    }

    public function testIsEnabledWithDateReturnFalse()
    {
        //date is "2016-01-21 01:02:03"
        $flag = 'flagDateFebruary';
        $response = $this->_featuredFlags->isEnabled($flag);
        $this->assertFalse($response);
    }

    public function testIsEnabledWithStatDateReturnTrue()
    {
        //date is "2016-01-21 01:02:03"
        $flag = 'flagDateStartJanuary';
        $response = $this->_featuredFlags->isEnabled($flag);
        $this->assertTrue($response);
    }

    public function testIsEnabledWithStatDateReturnFalse()
    {
        //date is "2016-01-21 01:02:03"
        $flag = 'flagDateStartFebruary';
        $response = $this->_featuredFlags->isEnabled($flag);
        $this->assertFalse($response);
    }

    public function testIsEnabledWithEndDateReturnTrue()
    {
        //date is "2016-01-21 01:02:03"
        $flag = 'flagDateEndFebruary';
        $response = $this->_featuredFlags->isEnabled($flag);
        $this->assertTrue($response);
    }

    public function testIsEnabledWithEndDateReturnFalse()
    {
        //date is "2016-01-21 01:02:03"
        $flag = 'flagDateEndJanuary';
        $response = $this->_featuredFlags->isEnabled($flag);
        $this->assertFalse($response);
    }

    public function testIsEnabledWithRealDateReturnFalse()
    {
        //date is "2016-01-21 01:02:03"
        $flag = 'flagDateEndAlways';
        $featuredFlags = new FeaturedFlagsImpl(self::$pdo, $this->_redis);
        $response = $featuredFlags->isEnabled($flag);
        $this->assertFalse($response);
    }

    private function _initCacheFlag($flagName, $filterParams, $value, $prefix)
    {
        $cacheKey = $prefix."FF_".$flagName.json_encode($filterParams);
        $RedisMock = $this->getMockBuilder('Redis')
            ->disableOriginalConstructor()
            ->setMethods(array('get'))
            ->getMock();
        $RedisMock->expects($this->at(0))
            ->method('get')
            ->with($this->equalTo($cacheKey))
            ->willReturn($value);
        $RedisMock->expects($this->at(1))
            ->method('get')
            ->with($this->equalTo($cacheKey))
            ->willReturn($value);
        $this->_featuredFlags->setRedis($RedisMock);
    }

    public function testCache()
    {
        $flag = 'flagCache';
        $featuredFlagsModel = new FeaturedFlagsModel(true);
        $this->_initCacheFlag($flag, null, $featuredFlagsModel, FeaturedFlagsImpl::ISENABLED_PREFIX);
        $response = $this->_featuredFlags->isEnabled($flag);
        $this->assertTrue($response);
    }

    public function testCacheGetValues()
    {
        $flag = 'flagCache';
        $returnMockValues = json_encode(array(
            'wii' => '123',
            'foo' => '321'
        ));
        $featuredFlagsModel = new FeaturedFlagsModel(true, $returnMockValues);
        $this->_initCacheFlag($flag, null, $featuredFlagsModel, FeaturedFlagsImpl::GETVALUES_PREFIX);
        $response = $this->_featuredFlags->getEnabledValues($flag);
        $this->assertEquals('123', $response['wii']);
        $this->assertEquals('321', $response['foo']);
    }

    public function testCacheWithParams()
    {
        $flag = 'flagCache';
        $featuredFlagsModel = new FeaturedFlagsModel(true);
        $params = array(
            'data' => '1',
            'data2' => '2'
        );
        $this->_initCacheFlag($flag, $params, $featuredFlagsModel, FeaturedFlagsImpl::ISENABLED_PREFIX);
        $response = $this->_featuredFlags->isEnabled($flag, $params);
        $this->assertTrue($response);
    }

    public function testGetEnabledValues()
    {
        $flag = 'flagReturnParams';
        $result = $this->_featuredFlags->getEnabledValues($flag);
        $this->assertEquals('example', $result['data']);
        $this->assertEquals('example2', $result['data2']);
    }

    public function testGetEnabledValuesReturnEmpty()
    {
        $flag = 'flagReturnParamsDisabled';
        $result = $this->_featuredFlags->getEnabledValues($flag);
        $this->assertEquals(array(), $result);
    }

    public function testGetEnabledValuesWithDates()
    {
        $flag = 'flagReturnParamsJanuary';
        $result = $this->_featuredFlags->getEnabledValues($flag);
        $this->assertEquals('example', $result['data']);
        $this->assertEquals('example2', $result['data2']);
    }

    public function testGetEnabledValuesWithDatesReturnEmpty()
    {
        $flag = 'flagReturnParamsFebruary';
        $result = $this->_featuredFlags->getEnabledValues($flag);
        $this->assertEquals(array(), $result);
    }

    public function testGetEnabledValuesWithParameters()
    {
        $flag = 'flagReturnParamsWithFilter';
        $params = array(
            'data1' => 'dt_true',
            'data2' => 'exist'
        );
        $result = $this->_featuredFlags->getEnabledValues($flag, $params);
        $this->assertEquals('example', $result['data']);
        $this->assertEquals('example2', $result['data2']);
    }

    public function testGetEnabledValuesWithParametersThatNotExist()
    {
        $flag = 'flagReturnParamsWithFilter';
        $params = array(
            'data1' => 'dt_true',
            'data2' => 'Not_exist'
        );
        $result = $this->_featuredFlags->getEnabledValues($flag, $params);
        $this->assertEquals(array(), $result);
    }
}
