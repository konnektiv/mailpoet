<?php

namespace MailPoet\Entities;

use DateTimeInterface;
use MailPoet\Doctrine\EntityTraits\AutoincrementedIdTrait;
use MailPoet\Doctrine\EntityTraits\CreatedAtTrait;
use MailPoet\Doctrine\EntityTraits\DeletedAtTrait;
use MailPoet\Doctrine\EntityTraits\UpdatedAtTrait;
use MailPoetVendor\Doctrine\Common\Collections\ArrayCollection;
use MailPoetVendor\Doctrine\Common\Collections\Collection;
use MailPoetVendor\Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="scheduled_tasks")
 */
class ScheduledTaskEntity {
  const STATUS_COMPLETED = 'completed';
  const STATUS_SCHEDULED = 'scheduled';
  const STATUS_PAUSED = 'paused';
  const STATUS_INVALID = 'invalid';
  const VIRTUAL_STATUS_RUNNING = 'running'; // For historical reasons this is stored as null in DB
  const PRIORITY_HIGH = 1;
  const PRIORITY_MEDIUM = 5;
  const PRIORITY_LOW = 10;
  const BASIC_RESCHEDULE_TIMEOUT = 5; // minutes
  const MAX_RESCHEDULE_TIMEOUT = 1440; // minutes

  use AutoincrementedIdTrait;
  use CreatedAtTrait;
  use UpdatedAtTrait;
  use DeletedAtTrait;

  /**
   * @ORM\Column(type="string", nullable=true)
   * @var string|null
   */
  private $type;

  /**
   * @ORM\Column(type="string", nullable=true)
   * @var string|null
   */
  private $status;

  /**
   * @ORM\Column(type="integer")
   * @var int
   */
  private $priority = 0;

  /**
   * @ORM\Column(type="datetimetz", nullable=true)
   * @var DateTimeInterface|null
   */
  private $scheduledAt;

  /**
   * @ORM\Column(type="datetimetz", nullable=true)
   * @var DateTimeInterface|null
   */
  private $processedAt;

  /**
   * @ORM\Column(type="json", nullable=true)
   * @var array|null
   */
  private $meta;

  /**
   * @ORM\Column(type="boolean", nullable=true)
   * @var bool|null
   */
  private $inProgress;

  /**
   * @ORM\Column(type="integer", options={"default" : 0})
   * @var int
   */
  private $rescheduleCount = 0;

  /**
   * @ORM\OneToMany(targetEntity="MailPoet\Entities\ScheduledTaskSubscriberEntity", mappedBy="task", fetch="EXTRA_LAZY")
   * @var Collection<int, ScheduledTaskSubscriberEntity>
   */
  public $subscribers;

  public function __construct() {
    $this->subscribers = new ArrayCollection();
  }

  /**
   * @return string|null
   */
  public function getType() {
    return $this->type;
  }

  /**
   * @param string|null $type
   */
  public function setType($type) {
    $this->type = $type;
  }

  /**
   * @return string|null
   */
  public function getStatus() {
    return $this->status;
  }

  /**
   * @param string|null $status
   */
  public function setStatus($status) {
    $this->status = $status;
  }

  /**
   * @return int
   */
  public function getPriority() {
    return $this->priority;
  }

  /**
   * @param int $priority
   */
  public function setPriority($priority) {
    $this->priority = $priority;
  }

  /**
   * @return DateTimeInterface|null
   */
  public function getScheduledAt() {
    return $this->scheduledAt;
  }

  /**
   * @param DateTimeInterface|null $scheduledAt
   */
  public function setScheduledAt($scheduledAt) {
    $this->scheduledAt = $scheduledAt;
  }

  /**
   * @return DateTimeInterface|null
   */
  public function getProcessedAt() {
    return $this->processedAt;
  }

  /**
   * @param DateTimeInterface|null $processedAt
   */
  public function setProcessedAt($processedAt) {
    $this->processedAt = $processedAt;
  }

  /**
   * @return array|null
   */
  public function getMeta() {
    return $this->meta;
  }

  /**
   * @param array|null $meta
   */
  public function setMeta($meta) {
    $this->meta = $meta;
  }

  /**
   * @return bool|null
   */
  public function getInProgress() {
    return $this->inProgress;
  }

  /**
   * @param bool|null $inProgress
   */
  public function setInProgress($inProgress) {
    $this->inProgress = $inProgress;
  }

  public function getRescheduleCount(): int {
    return $this->rescheduleCount;
  }

  public function setRescheduleCount(int $rescheduleCount) {
    $this->rescheduleCount = $rescheduleCount;
  }

  /**
   * @return Collection<int, ScheduledTaskSubscriberEntity>
   */
  public function getSubscribers(): Collection {
    return $this->subscribers;
  }
}
