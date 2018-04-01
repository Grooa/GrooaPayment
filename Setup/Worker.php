<?php
namespace Plugin\GrooaPayment;

use Ip\Exception;
use \Ip\Internal\Plugins\Service as PluginService;
use \Plugin\GrooaPayment\Model\TrackOrder;
use \Plugin\Track\Model\Module;

class Worker {

    private $orderTable;

    public function __construct()
    {
        $this->orderTable = ipTable(TrackOrder::TABLE);
    }

    public function activate() {
        self::requiredPluginsExists(['User', 'Track', 'Composer']);

        new \PayPal\Api\Payment();

//        $userTable = ipTable('user');
//        $trackTable = ipTable(Track::TABLE);
//
//        $sql = "
//         CREATE TABLE IF NOT EXISTS $this->orderTable (
//          `orderId` INT(11) NOT NULL AUTO_INCREMENT,
//          `type` VARCHAR(128),
//          `createdOn` DATETIME DEFAULT CURRENT_TIMESTAMP,
//    	  `userId` INT(11) NOT NULL,
//		  `trackId` INT(11) NOT NULL,
//		  `payerId` VARCHAR (255),
//		  `paymentId` VARCHAR(255),
//		  `saleId` VARCHAR (255),
//		  `state` VARCHAR(128),
//		  `completed` DATETIME,
//		  `paymentExecuted` DATETIME,
//		  `invoiceNumber` VARCHAR(255),
//
//          FOREIGN KEY (`userId`)
//            REFERENCES $userTable (`id`)
//            ON DELETE CASCADE
//            ON UPDATE CASCADE,
//
//
//          FOREIGN KEY (`trackId`)
//            REFERENCES $trackTable (`trackId`),
//
//          PRIMARY KEY (`orderId`)
//
//        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
//        ";
//
//        ipDb()->execute($sql);
    }

    public function remove() {
        // As this is critical data, we don't want it to disappear
        // should the plugin be moved, or replaced
//        ipDb()->execute("DROP TABLE $this->orderTable");
    }

    /**
     * @param $requiredPlugins
     * @throws \Ip\Exception
     */
    private static function requiredPluginsExists($requiredPlugins) {
        $plugins = PluginService::getActivePluginNames();

        foreach ($requiredPlugins as $required) {
            if (!in_array($required, $plugins)) {
                throw new Exception("GrooaPayment requires plugin: $required to function. Install and activate it first");
            }
        }
    }

    private static function requiredPayPalExists() {
        if (!class_exists(\PayPal\Api\Payment)) {
            throw new Exception("GrooaPayment requires Composer package `paypal/rest-api-sdk-php`");
        }
    }
}