<?php
namespace YMF\Services;

class BaseService
{
    protected $usr;

    /**
     * Create a new response.
     *
     * @return void
     */
    public function __construct()
    {
        global $_USR;
        $this->usr = $_USR;
    }
}
