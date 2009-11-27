<?php

class Horde_Icalendar_Vfreebusy extends Horde_Icalendar_Base
{
    /**
     * Constructor.
     */
    public function __construct($properties = array())
    {
        $this->_properties += array(
            'uid' => array('required' => true,
                           'multiple' => false,
                           'type' => 'string'),
            'start' => array('required' => true,
                             'multiple' => false,
                             'class' => 'Horde_Date'),
            'stamp' => array('required' => true,
                             'multiple' => false,
                             'class' => 'Horde_Date'),
        );
        parent::__construct($properties);
    }

    /**
     * Validates a property-value-pair.
     *
     * @throws InvalidArgumentException
     */
    protected function _validate($property, &$value)
    {
        parent::_validate($property, $value);
        if ($property == 'start') {
            $value->setTimezone('UTC');
        }
    }

}
