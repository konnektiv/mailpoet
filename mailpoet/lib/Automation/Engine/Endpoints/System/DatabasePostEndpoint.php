<?php declare(strict_types = 1);

namespace MailPoet\Automation\Engine\Endpoints\System;

use MailPoet\Automation\Engine\API\Endpoint;
use MailPoet\Automation\Engine\API\Request;
use MailPoet\Automation\Engine\API\Response;
use MailPoet\Automation\Engine\Migrations\Migrator;

class DatabasePostEndpoint extends Endpoint {
  /** @var Migrator */
  private $migrator;

  public function __construct(
    Migrator $migrator
  ) {
    $this->migrator = $migrator;
  }

  public function handle(Request $request): Response {
    $this->migrator->deleteSchema();
    $this->migrator->createSchema();
    return new Response(null);
  }
}
