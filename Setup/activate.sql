CREATE TABLE IF NOT EXISTS ip_track_order (
  `orderId` INT(11) NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(128),
  `createdOn` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `userId` INT(11) NOT NULL,
  `trackId` INT(11) NOT NULL,
  `payerId` VARCHAR (255),
  `paymentId` VARCHAR(255),
  `saleId` VARCHAR (255),
  `state` VARCHAR(128),
  `completed` DATETIME,
  `paymentExecuted` DATETIME,
  `invoiceNumber` VARCHAR(255),
  `override` BOOL DEFAULT FALSE,
  `overrideReason` TEXT,
  `overridePrice` FLOAT DEFAULT NULL,
  `isSandbox` BOOL DEFAULT FALSE,

  FOREIGN KEY (`userId`)
    REFERENCES ip_user (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  FOREIGN KEY (`trackId`)
    REFERENCES ip_track (`trackId`),

  PRIMARY KEY (`orderId`)

) ENGINE=MyISAM  DEFAULT CHARSET=utf8;