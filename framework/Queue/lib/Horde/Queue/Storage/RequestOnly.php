<?php
/**
 * Stores queue tasks in the current request. No persistence.
 */
class Horde_Queue_Storage_RequestOnly extends Horde_Queue_Storage_Base
{
    protected $_tasks = array();

    public function add($task)
    {
        $this->_tasks[] = $task;
    }

    public function getMany($num = 50)
    {
        return array_splice($this->_tasks, 0, $num);
    }

}
