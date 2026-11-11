<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Paweł Kuffel <pawel@kuffel.io>
 *
 * @author Paweł Kuffel <pawel@kuffel.io>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Webhooks\Utils;

use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;
use Throwable;

class SignedRequest {

	public static function sendSignedRequest(array $eventDto, $secret, $endpoint): void {
		$eventJSON = json_encode($eventDto, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if ($eventJSON === false) {
			$eventJSON = json_encode($eventDto);
		}

		$secretStr = is_string($secret) ? $secret : '';
		$hmacSignature = hash_hmac('sha256', (string)$eventJSON, $secretStr);
		$legacyHash = hash('sha256', (string)$eventJSON . $secretStr);

		/** @var LoggerInterface|null $logger */
		$logger = null;
		try {
			if (class_exists('\\OC') && isset(\OC::$server)) {
				$logger = \OC::$server->get(LoggerInterface::class);
			}
		} catch (Throwable $e) {
			// ignore
		}

		try {
			/** @var IClientService|null $clientService */
			$clientService = null;
			if (class_exists('\\OC') && isset(\OC::$server)) {
				$clientService = \OC::$server->get(IClientService::class);
			}

			if ($clientService !== null) {
				$client = $clientService->newClient();
				$headers = [
					'Content-Type' => 'application/json',
					'X-Nextcloud-Webhooks' => $hmacSignature,
					'X-Nextcloud-Webhooks-Signature' => 'sha256=' . $hmacSignature,
					'X-Nextcloud-Webhooks-Legacy' => $legacyHash,
				];

				$response = $client->post((string)$endpoint, [
					'headers' => $headers,
					'body' => $eventJSON,
					'timeout' => 10,
					'connect_timeout' => 5,
				]);

				$status = $response->getStatusCode();
				if ($status >= 200 && $status < 300) {
					if ($logger !== null) {
						$logger->debug("Webhook delivered to {$endpoint} (HTTP {$status})", ['app' => 'webhooks']);
					}
				} else {
					if ($logger !== null) {
						$logger->warning("Webhook delivery to {$endpoint} returned HTTP {$status}", ['app' => 'webhooks']);
					}
				}
				return;
			}
		} catch (Throwable $e) {
			if ($logger !== null) {
				$logger->warning("Webhook delivery to {$endpoint} failed: " . $e->getMessage(), ['app' => 'webhooks']);
			}
		}

		// Fallback to curl if clientService could not be executed
		if (function_exists('exec')) {
			$eventJSONescaped = escapeshellarg((string)$eventJSON);
			$endpointEscaped = escapeshellarg((string)$endpoint);
			$curl  = "curl $endpointEscaped --header \"X-Nextcloud-Webhooks: $hmacSignature\" ";
			$curl .= "--header \"X-Nextcloud-Webhooks-Signature: sha256=$hmacSignature\" ";
			$curl .= "--header \"Content-Type: application/json\" --request POST ";
			$curl .= "--data $eventJSONescaped > /dev/null 2>&1 &";
			@exec($curl);
		}
	}
}
