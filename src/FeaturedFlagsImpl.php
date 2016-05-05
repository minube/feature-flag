<?php

namespace FeaturedFlags;

use PDO;
use Redis;

class FeaturedFlagsImpl implements FeaturedFlags
{
    protected $_pdo;
    protected $_redis;
    protected $_date;

    CONST TABLE_NAME = 'featured_flags';
    CONST ISENABLED_PREFIX = 'isEnabled_';
    CONST GETVALUES_PREFIX = 'getEnabledValues_';

    /**
     * FeaturedFlags constructor.
     * @param PDO $pdo
     * @param Redis|null $redis
     * @param string|null $date
     */
    public function __construct(PDO $pdo, Redis $redis = null, $date = null)
    {
        $this->_pdo = $pdo;
        if ($redis) {
            $this->_redis = $redis;
        }
        if (!is_null($date)) {
            $this->_date = $date;
        }
    }

    /**
     * @param PDO $pdo
     * @param Redis $redis
     * @return FeaturedFlags
     */
    public static function getInstance(PDO $pdo, Redis $redis = null) {
        return new self($pdo, $redis);
    }

    /**
     * @param string $flagName
     * @param null|array $filterParams
     * @return boolean
     */
    public function isEnabled($flagName, $filterParams = null)
    {
        $cacheKey = $this->_getKey($flagName, $filterParams, self::ISENABLED_PREFIX);
        $cacheModel = $this->_getCacheModel($cacheKey);
        if ($cacheModel instanceof FeaturedFlagsModel)
        {
            return $cacheModel->isEnabled();
        }

        $featureFlagModel = $this->_getBDFlag($flagName, $filterParams);
        $this->_setCacheKey($cacheKey, $featureFlagModel);
        return $featureFlagModel->isEnabled();
    }

    /**
     * @param string $flagName
     * @param null|array $filterParams
     * @return array
     */
    public function getEnabledValues($flagName, $filterParams = null)
    {
        $cacheKey = $this->_getKey($flagName, $filterParams, self::GETVALUES_PREFIX);
        $cacheValue = $this->_getCacheModel($cacheKey);
        if ($cacheValue instanceof FeaturedFlagsModel)
        {
            return $cacheValue->getParamsArray();
        }

        $featureFlagModel = $this->_getBDFlag($flagName, $filterParams);
        $this->_setCacheKey($cacheKey, $featureFlagModel);
        return $featureFlagModel->getParamsArray();
    }

    /**
     * @param Redis $redis
     */
    public function setRedis(Redis $redis)
    {
        $this->_redis = $redis;
    }

    /**
     * @param string $flagName
     * @param null|array $filterParams
     * @return FeaturedFlagsModel
     */
    private function _getBDFlag($flagName, $filterParams)
    {
        $flagsData = $this->_getDBFlagsData($flagName);
        foreach ($flagsData as $flag) {
            if ($this->_checkParams($flag['params'], $filterParams))
            {
                return new FeaturedFlagsModel(true, $flag['return_params'], $flag['end_date']);
            }
        }

        return new FeaturedFlagsModel(false);
    }

    /**
     * @param string $cacheKey
     * @return FeaturedFlagsModel
     */
    private function _getCacheModel($cacheKey)
    {
        try {
            if (!is_null($this->_redis) && $this->_redis->get($cacheKey))
            {
                return $this->_redis->get($cacheKey);
            }
        } catch (\Exception $exc) {
        }
        return null;
    }

    /**
     * @param string $cacheKey
     * @param FeaturedFlagsModel $featuredFlagsModel
     */
    private function _setCacheKey($cacheKey, FeaturedFlagsModel $featuredFlagsModel)
    {
        if (!is_null($this->_redis)) {
            try {
                $timeOut = $this->_getTimeout($featuredFlagsModel->getEndDate());
                $this->_redis->set($cacheKey, $featuredFlagsModel, $timeOut);
            } catch (\Exception $e){}
        }
    }

    /**
     * @param string $query
     * @param array $params
     * @return \mysqli_stmt
     */
    private function _getStmt($query, $params)
    {
        $stmt = $this->_pdo->prepare($query);
        foreach ($params as $field => $value) {
            $stmt->bindValue(":$field", $value);
        }
        return $stmt;
    }

    /**
     * @param string $flagName
     * @return array
     */
    private function _getDBFlagsData($flagName)
    {
        $query = "SELECT * FROM ".self::TABLE_NAME." WHERE 
        name = :flag AND 
        status = 1 AND
        (   (start_date IS NULL AND end_date IS NULL)
                OR
            (start_date <= :now AND :now <= end_date)
                OR
            (:now <= end_date AND (start_date IS NULL OR start_date = ''))
                OR
            (start_date <= :now AND (end_date IS NULL OR end_date = ''))
        )";

        $params = array(
            'flag' => $flagName,
            'now'  =>  $this->_getDate()
        );

        return $this->_executeForSelect($query, $params);
    }

    /**
     * @param string $query
     * @param array $params
     * @return array
     */
    private function _executeForSelect($query, $params = array())
    {
        $stmt = $this->_getStmt($query, $params);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @return string
     */
    private function _getDate() {
        if ($this->_date) {
            return $this->_date;
        }

        return date("Y-m-d H:i:s");
    }

    /**
     * @param array $flagParams
     * @param array $filterParams
     * @return boolean
     */
    private function _compareParams($flagParams, $filterParams)
    {
        $checkParams = true;
        foreach ($filterParams as $key => $value)
        {
            $checkParams = $checkParams && isset($flagParams[$key]) && $flagParams[$key] == $value;
        }

        return $checkParams;
    }

    /**
     * @param string $flagParamsJson
     * @param null|array $filterParams
     * @return boolean
     */
    private function _checkParams($flagParamsJson, $filterParams = null)
    {
        if (is_null($filterParams)) {
            return true;
        }

        $flagParamsData = json_decode($flagParamsJson, true);
        if (!is_array($flagParamsData) || count($flagParamsData) === 0) {
            return false;
        }

        return $this->_compareParams($flagParamsData, $filterParams);
    }

    /**
     * @param string $flagName
     * @param array|null $filterParams
     * @param string $prefix
     * @return boolean
     */
    private function _getKey($flagName, $filterParams = null, $prefix = "")
    {
        return $prefix."FF_".$flagName.json_encode($filterParams);
    }

    /**
     * @param string $endDate
     * @return int
     */
    private function _getTimeout($endDate)
    {
        if (!$endDate) {
            return 0;
        }

        return strtotime($endDate) - strtotime($this->_getDate());
    }
}
