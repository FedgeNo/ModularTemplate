-- The authoritative schema. bin/install.php (and the web setup wizard) parse
-- this file: each CREATE TABLE is created when its table is missing, and
-- schema drift on existing tables (new columns, indexes, foreign keys) is
-- reconciled against it - see SchemaInstaller. Statements outside the CREATE
-- TABLE blocks run on every install/upgrade: plain DML as idempotent
-- maintenance, ALTER TABLE statements (guarded with IF [NOT] EXISTS) as index
-- migrations. Never hand-run ALTERs against a live database - add them here
-- and bump APP_VERSION in src/init.php so the upgrade fires.

CREATE TABLE `Users` (
  `userId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `passwordHash` varchar(255) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `banned` tinyint(1) NOT NULL DEFAULT 0,
  `banReason` text DEFAULT NULL,
  `isMod` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  -- The language this member asked to read the site in. Null until they say,
  -- and only ever set by them saying so - a browser's Accept-Language is a
  -- guess that answers for them until then, and re-guessing on every browser
  -- they open is what having said so is meant to stop.
  `locale` varchar(5) DEFAULT NULL,
  `theme` varchar(10) NOT NULL DEFAULT 'system',
  -- When this member was last here, to the nearest few minutes - init.php
  -- writes it on a request from somebody signed in, but only once the stored
  -- value has gone stale, so reading a long thread is one write rather than
  -- one per page. Null for an account that has never signed in.
  `lastSeen` datetime DEFAULT NULL,
  `sessionVersion` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`userId`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `EmailVerifications` (
  `verificationId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `tokenHash` varchar(64) NOT NULL,
  `expiresAt` datetime NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`verificationId`),
  KEY `tokenHash` (`tokenHash`),
  KEY `expiresAt` (`expiresAt`),
  KEY `userId` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `PasswordResets` (
  `resetId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `tokenHash` varchar(64) NOT NULL,
  `expiresAt` datetime NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`resetId`),
  KEY `tokenHash` (`tokenHash`),
  KEY `expiresAt` (`expiresAt`),
  KEY `userId` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `EmailChangeReverts` (
  `revertId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `previousEmail` varchar(255) NOT NULL,
  `tokenHash` varchar(64) NOT NULL,
  `expiresAt` datetime NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`revertId`),
  KEY `tokenHash` (`tokenHash`),
  KEY `expiresAt` (`expiresAt`),
  KEY `userId` (`userId`),
  UNIQUE KEY `previousEmail` (`previousEmail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `RateLimitAttempts` (
  `attemptId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rateKey` varchar(255) NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attemptId`),
  KEY `rateKey` (`rateKey`,`createdAt`),
  KEY `createdAt` (`createdAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Settings` (
  `name` varchar(64) NOT NULL,
  `value` text DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
