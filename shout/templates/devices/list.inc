<div class="header">
    <ul id="controls">
        <?php
        $addurl = Horde::applicationUrl('devices.php');
        $addurl = Horde_Util::addParameter($addurl, 'action', 'add');
        ?>
        <li><a href="<?php echo $addurl; ?>">
            <?php echo Horde::img('add-device.png'); ?>&nbsp;New Device
            </a>
        </li>
    </ul>
    Context: <?php echo $context; ?>
</div>

<div id="extensionList">
    <table width="100%" cellspacing="0" class="striped">
        <tr>
            <td class="uheader">Device ID</td>
            <td class="uheader">Mailbox</td>
            <td class="uheader">CallerID</td>
        </tr>
        <?php
            $line = 0;
            foreach ($devices as $devid => $info) {

                $url = Horde::applicationUrl("devices.php");
                $url = Horde_Util::addParameter($url,
                    array(
                        'devid' => $devid,
                    )
                );
                $editurl = Horde_Util::addParameter($url, 'action', 'edit');
                $deleteurl = Horde_Util::addParameter($url, 'action', 'delete');
        ?>
        <tr class="item">
            <td style="width: 20%">
                <?php echo Horde::link($editurl); echo $devid; ?></a>
            </td>
            <td style="width: 35%;">
                <?php echo $info['mailbox']; ?>
            </td>
            <td style="width: 45%">
                <?php echo $info['callerid']; ?>
            </td>
        </tr>
        <?php
            }
        ?>
    </table>
</div>