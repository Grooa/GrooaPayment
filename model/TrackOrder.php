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

    /**
     * Will fetch the first purchase by a user, which is completed.
     *
     * In the PayPal checkout process,
     * we must store some initial information before completing the checkout.
     * To distinguish between a _created one_ and a _completed one_, we require the `saleId` to be populated,
     * and `state` to be `pending` or `completed`.
     *
     *  - When `pending`, we have received the purchase and the user has confirmed the payment,
     *    and we have received a `saleId`.
     *  - When `completed`, our webhook connected to PayPal has received confirmation of the purchase,
     *    and the process should be complete.
     */
    public static function getPurchasedByTrackAndUser($trackId, $userId) {
        $rows = self::getAll(['trackId' => $trackId, 'userId' => $userId]);

        foreach ($rows as $r) {
            // If autogenerated, we must require a saleId to distinguish between
            //  a _created order_ and a _completed one_
            // If manually added, we only require `override` to be checked of.
            //  This is to prevent accidentally adding purchases which cannot be tracked.
            if ((!empty($r['saleId']) || (!empty($r['override']) && $r['override'] == true)) &&
                ($r['state'] == 'pending' || $r['state'] == 'completed')) {
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

    /**
     * Based on the $trackId and $userId,
     * will it check if the user has purchased this product
     */
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