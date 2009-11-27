<?php
class Horde_Queue_Runner_RequestShutdown extends Horde_Queue_Runner_Base
{
    public function __destruct()
    {
        foreach ($this->_storage->getMany() as $task) {
            $task->run();
        }
    }

}