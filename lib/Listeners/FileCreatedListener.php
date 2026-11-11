<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 André Théo LAURET <andrelauret@eclipse-technology.eu>
 *
 * @license GNU AGPL version 3 or any later version
 */

namespace OCA\Webhooks\Listeners;

use OCA\Webhooks\Utils\DtoExtractor;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCreatedEvent;

class FileCreatedListener extends AbstractListener implements IEventListener {

	public const CONFIG_NAME = "webhooks_file_created_url";

	public function handleIncomingEvent(Event $event): ?array {
		if (!($event instanceof NodeCreatedEvent)) {
			return null;
		}

		return [
			'node' => DtoExtractor::buildNodeDto($event->getNode()),
		];
	}
}
