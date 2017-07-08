<?php
/**
 * @package   ImpressPages
 */

namespace Plugin\GrooaPayment;
use Plugin\Track\Model\Track;

class AdminController
{
    /**
     * @ipSubmenu Online Course
     */
    public function index() {
        $config = [
            'title' => 'Online Course purchases',
            'table' => 'track_order',
            'idField' => 'orderId',
            'fields' => [
                [
                    'field' => 'orderId',
                    'label' => 'id',
                    'type' => 'Integer',
                    'ignoreDb' => true // Autogenerated
                ],
                [
                    'field' => 'userId',
                    'label' => 'User Id',
                    'type' => 'Integer'
                ],
                [
                    'field' => 'trackId',
                    'label' => 'Track',
                    'type' => 'Select',
                    'values' => Track::findWithIdAndTitle()
                ],
                [
                    'field' => 'createdOn',
                    'label' => 'Created on',
                    'ignoreDb' => true // Autogenerated
                ],
                [
                    'field' => 'type',
                    'label' => 'Payment Type',
                    'type' => 'Text'
                ],
                [
                    'field' => 'payerId',
                    'label' => 'payerID',
                    'type' => 'Text',
                    'hint' => "PayPal's Id for the payer"
                ],
                [
                    'field' => 'paymentId',
                    'label' => 'paymentID',
                    'type' => 'Text',
                    'hint' => "PayPal's Id for the created payment"
                ],
                [
                    'field' => 'saleId',
                    'label' => 'saleID',
                    'type' => 'Text',
                    'hint' => "PayPal's Id for the executed sale"
                ],
                [
                    'field' => 'state',
                    'label' => 'Payment State',
                    'type' => 'Select',
                    'default' => 'none',
                    'values' => [
                        'none',
                        'pending',
                        'canceled',
                        'completed',
                        'rejected'
                    ]
                ],
                [
                    'field' => 'completed',
                    'label' => 'Completed',
                    'type' => 'Text',
                    'default' => null
                ],
                [
                    'field' => 'paymentExecuted',
                    'label' => 'Payment Executed',
                    'type' => 'Text',
                    'default' => null,
                    'hint' => 'DateTime when payment has been executed (with state pending)'
                ],
                [
                    'field' => 'invoiceNumber',
                    'label' => 'Invoice Number',
                    'type' => 'Text',
                    'ignoreDb' => true
                ]
            ],
            'pageSize' => 15
        ];

        return ipGridController($config);
    }

}