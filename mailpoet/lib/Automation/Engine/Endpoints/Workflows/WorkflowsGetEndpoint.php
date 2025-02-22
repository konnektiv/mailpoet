<?php declare(strict_types = 1);

namespace MailPoet\Automation\Engine\Endpoints\Workflows;

use DateTimeImmutable;
use MailPoet\Automation\Engine\API\Endpoint;
use MailPoet\Automation\Engine\API\Request;
use MailPoet\Automation\Engine\API\Response;
use MailPoet\Automation\Engine\Storage\WorkflowStorage;
use MailPoet\Automation\Engine\Workflows\Workflow;

class WorkflowsGetEndpoint extends Endpoint {
  /** @var WorkflowStorage */
  private $workflowStorage;

  public function __construct(
    WorkflowStorage $workflowStorage
  ) {
    $this->workflowStorage = $workflowStorage;
  }

  public function handle(Request $request): Response {
    $workflows = $this->workflowStorage->getWorkflows();
    return new Response(array_map(function (Workflow $workflow) {
      return $this->buildWorkflow($workflow);
    }, $workflows));
  }

  private function buildWorkflow(Workflow $workflow): array {
    return [
      'id' => $workflow->getId(),
      'name' => $workflow->getName(),
      'status' => $workflow->getStatus(),
      'created_at' => $workflow->getCreatedAt()->format(DateTimeImmutable::W3C),
      'updated_at' => $workflow->getUpdatedAt()->format(DateTimeImmutable::W3C),
    ];
  }
}
