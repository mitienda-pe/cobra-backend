<?php

namespace Config;

use CodeIgniter\CLI\Commands;
use CodeIgniter\CLI\BaseCommand;

class CommandsConfig extends Commands
{
    /**
     * --------------------------------------------------------------------------
     * Commands Paths
     * --------------------------------------------------------------------------
     *
     * This array defines the core framework commands available to the CLI.
     * These should not be modified.
     *
     * @var array<string, string[]>
     */
    public $commands = [
        'app' => [
            \App\Commands\MigratePaymentsToInstalments::class,
        ],
    ];
}
