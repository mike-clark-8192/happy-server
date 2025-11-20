<?php

namespace Happy\App\Api\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Happy\Storage\Models\Machine;

/**
 * Machine API controller - handles machine registration and management.
 */
class MachineController
{
    /**
     * List user machines.
     * GET /v1/machines
     */
    public function list(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $params = $request->getQueryParams();

        $query = Machine::where('account_id', $user->id)
            ->orderBy('last_alive_at', 'desc');

        // Filter by online status
        if (isset($params['online']) && $params['online'] === 'true') {
            $query->online();
        }

        $machines = $query->get();

        $result = [
            'machines' => $machines->map(fn($m) => $this->formatMachine($m))->toArray(),
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Register or update machine (upsert).
     * POST /v1/machines
     */
    public function register(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];

        $machineId = $data['id'] ?? null;

        if ($machineId) {
            // Try to find existing machine
            $machine = Machine::where('id', $machineId)
                ->where('account_id', $user->id)
                ->first();

            if ($machine) {
                // Update existing machine
                if (isset($data['name'])) {
                    $machine->name = $data['name'];
                }
                if (isset($data['daemonState'])) {
                    $machine->setDaemonState($data['daemonState']);
                }
                $machine->last_alive_at = now();
                $machine->save();

                $response->getBody()->write(json_encode($this->formatMachine($machine)));
                return $response->withHeader('Content-Type', 'application/json');
            }
        }

        // Create new machine
        $machine = new Machine([
            'id' => $machineId ?? Machine::generateId(),
            'account_id' => $user->id,
            'name' => $data['name'] ?? 'Unknown',
            'last_alive_at' => now(),
        ]);

        if (isset($data['daemonState'])) {
            $machine->setDaemonState($data['daemonState']);
        }

        $machine->save();

        $response->getBody()->write(json_encode($this->formatMachine($machine)));
        return $response
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get single machine.
     * GET /v1/machines/{id}
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $machineId = $args['id'];

        $machine = Machine::where('id', $machineId)
            ->where('account_id', $user->id)
            ->first();

        if (!$machine) {
            $response->getBody()->write(json_encode(['error' => 'Machine not found']));
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($this->formatMachine($machine)));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Update machine.
     * PUT /v1/machines/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $machineId = $args['id'];
        $data = $request->getParsedBody() ?? [];

        $machine = Machine::where('id', $machineId)
            ->where('account_id', $user->id)
            ->first();

        if (!$machine) {
            $response->getBody()->write(json_encode(['error' => 'Machine not found']));
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        if (isset($data['name'])) {
            $machine->name = $data['name'];
        }
        if (isset($data['daemonState'])) {
            $machine->setDaemonState($data['daemonState']);
        }

        // Touch to update last_alive_at
        $machine->last_alive_at = now();
        $machine->save();

        $response->getBody()->write(json_encode($this->formatMachine($machine)));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function formatMachine(Machine $machine): array
    {
        return [
            'id' => $machine->id,
            'accountId' => $machine->account_id,
            'name' => $machine->name,
            'daemonState' => $machine->getDaemonState(),
            'version' => $machine->version,
            'lastAliveAt' => $machine->last_alive_at?->toIso8601String(),
            'isOnline' => $machine->isOnline(),
            'createdAt' => $machine->created_at?->toIso8601String(),
            'updatedAt' => $machine->updated_at?->toIso8601String(),
        ];
    }
}
