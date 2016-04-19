<?php

namespace FeaturedFlags;

class FeaturedFlagsModel
{
    protected $_status;
    protected $_data;
    protected $_endDate;

    public function __construct($status, $data = array(), $endDate = null)
    {
        $this->_status = $status;
        $this->_data = $data;
        $this->_endDate = $endDate;
    }

    /**
     * @return array
     */
    public function getParamsArray()
    {
        if (is_string($this->_data)) {
            return json_decode($this->_data, true);
        } else {
            return array();
        }
    }

    public function isEnabled()
    {
        return $this->_status == true;
    }


    public function getEndDate()
    {
        return $this->_endDate;
    }
}
