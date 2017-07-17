<?php

namespace Plugin\GrooaPayment\Model;

use Ip\Exception;
use Plugin\Track\Model\Track;

class TrackOrder
{

    const TABLE = 'track_order';
    private static $unresolvedOrderLifetime = 45; // minutes

    public static function get($query)
    {
        return ipDb()->selectRow(self::TABLE, '*', $query);
    }

    public static function getAll($query)
    {
        return ipDb()->selectAll(self::TABLE, '*', $query);
    }

    public static function getBySaleId($id)
    {
        return self::get(['saleId' => $id]);
    }

    public static function getByTrackAndUser($trackId, $userId)
    {
        return self::get(['trackId' => $trackId, 'userId' => $userId]);
    }

    public static function getPurchasedByTrackAndUser($trackId, $userId) {
        $rows = self::getAll(['trackId' => $trackId, 'userId' => $userId]);


        foreach ($rows as $r) {
            if (!empty($r['saleId']) && ($r['state'] == 'pending' || $r['state'] == 'completed')) {
                return $r;
            }
        }

        return null;
    }

    public static function getByUserId($uid) {
        $sql = "SELECT * FROM ". ipTable(Track::TABLE) ." AS tracks, 
                  (SELECT trackId FROM " . ipTable(self::TABLE) ." WHERE `userId`=" . esc($uid) . ") AS ordered 
                WHERE tracks.trackId = ordered.trackId;";

        return ipDb()->fetchAll($sql);
    }

    public static function getByTrackUserAndPaymentId($trackId, $userId, $paymentId) {
        return self::get(['trackId' => $trackId, 'userId' => $userId, 'paymentId' => $paymentId]);
    }

    public static function getByPaymentId($id)
    {
        return self::getAll(['paymentId' => $id]);
    }

    public static function create($fields)
    {
        if (!empty($field['orderId'])) {
            unset($field['orderId']);
        }

        if (empty($fields['type'])) {
            $fields['type'] = 'paypal'; // Treat paypal as default value
        }

        if (!empty($fields['createdOn'])) {
            unset($fields['createdOn']);
        }

        if (empty($fields['userId']) || empty($fields['trackId'])) {
            throw new Exception("Missing required field `userId` or `trackId`");
        }

        if (empty($fields['paymentId'])) {
            throw new Exception("Missing required field `paymentId`");
        }

        return ipDb()->insert(self::TABLE, $fields);
    }

    public static function clearUnresolvedOrders() {
        $now = new \DateTime();

        $result = ipDb()->execute(
            "DELETE FROM ". ipTable(self::TABLE) . " WHERE saleId IS NULL AND " .
            "(createdOn + INTERVAL " . self::$unresolvedOrderLifetime . " MINUTE) < NOW();"
        ); // Give the orders some time to finish before deleting them

        return $result != 0;
    }

    public static function update($id, $fields)
    {
        return ipDb()->update(
            self::TABLE,
            $fields,
            ['orderId' => $id]
        );
    }

    public static function hasPurchased($trackId, $userId)
    {
        $row = self::getPurchasedByTrackAndUser($trackId, $userId);

        if (!$row || empty($row['state'])) {
            return false;
        }

        // Accept pending as an acceptable state. Requires the `state` to be
        // update frequently to completed, cancelled, etc.
        if ($row['state'] == 'completed' || $row['state'] == 'pending') {
            return true;
        }

        return false;
    }

    /**
     * Will set the order to `completed`, and set it's timestamp
     * @param int $orderId
     */
    public static function completeOrder($orderId)
    {

    }

    public static function cancelOrder($orderId)
    {

    }

    /**
     * Called when payment has been executed,
     * but not necessarily confirmed
     *
     * @param int $orderId
     * @param string $paymentId
     * @param string $payerId
     * @param string $saleId
     * @param string $state
     */
    public static function executePayment($orderId, $paymentId, $payerId, $saleId, $state = "pending")
    {

    }
}