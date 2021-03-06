<?php
// Copyright (c) 2016 Interfacelab LLC. All rights reserved.
//
// Released under the GPLv3 license
// http://www.gnu.org/licenses/gpl-3.0.html
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// **********************************************************************

namespace ILAB\Docs\CLI;

if (!defined('ABSPATH')) { header('Location: /'); die; }

abstract class Command extends \WP_CLI_Command {
	public static function Warn($string) {
		\WP_CLI::warning($string);
	}

	public static function Error($string) {
		\WP_CLI::error($string);
	}

	public static function Info($string, $newline = false) {
		\WP_CLI::out(\WP_CLI::colorize($string).($newline ? "\n" : ""));
	}

	public static function Out($string, $newline = false) {
		\WP_CLI::out($string.($newline ? "\n" : ""));
	}

	public abstract static function Register();
}