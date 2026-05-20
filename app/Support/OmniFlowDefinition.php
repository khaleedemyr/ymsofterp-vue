<?php

namespace App\Support;

class OmniFlowDefinition
{
    public const VERSION_GRAPH = 2;

    /**
     * @param  array<string, mixed>|null  $definition
     */
    public static function isGraph(?array $definition): bool
    {
        return is_array($definition)
            && isset($definition['nodes'])
            && is_array($definition['nodes']);
    }

    /**
     * @param  array<string, mixed>|null  $definition
     * @return list<array<string, mixed>>
     */
    public static function steps(?array $definition): array
    {
        if (! is_array($definition)) {
            return [];
        }

        if (self::isGraph($definition)) {
            return self::graphToLinearSteps($definition);
        }

        $steps = $definition['steps'] ?? [];

        return is_array($steps) ? array_values($steps) : [];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function actionNodeCount(array $definition): int
    {
        if (! self::isGraph($definition)) {
            return count($definition['steps'] ?? []);
        }

        $count = 0;
        foreach ($definition['nodes'] as $node) {
            if (! is_array($node)) {
                continue;
            }
            $type = self::nodeType($node);
            if ($type !== '' && $type !== 'trigger') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public static function nodeType(array $node): string
    {
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];

        return (string) ($data['nodeType'] ?? $node['type'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public static function nodeConfig(array $node): array
    {
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        $config = $data['config'] ?? [];

        return is_array($config) ? $config : [];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, array<string, mixed>>
     */
    public static function nodesById(array $definition): array
    {
        $map = [];
        foreach ($definition['nodes'] ?? [] as $node) {
            if (! is_array($node)) {
                continue;
            }
            $id = (string) ($node['id'] ?? '');
            if ($id !== '') {
                $map[$id] = $node;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, list<array<string, mixed>>>
     */
    public static function outgoingEdges(array $definition): array
    {
        $bySource = [];
        foreach ($definition['edges'] ?? [] as $edge) {
            if (! is_array($edge)) {
                continue;
            }
            $source = (string) ($edge['source'] ?? '');
            if ($source === '') {
                continue;
            }
            $bySource[$source][] = $edge;
        }

        return $bySource;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function findTriggerNodeId(array $definition): ?string
    {
        foreach ($definition['nodes'] ?? [] as $node) {
            if (! is_array($node)) {
                continue;
            }
            if (self::nodeType($node) === 'trigger') {
                return (string) ($node['id'] ?? '');
            }
        }

        return null;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $outgoing
     */
    public static function nextNodeId(string $sourceId, array $outgoing, ?string $sourceHandle = null): ?string
    {
        $edges = $outgoing[$sourceId] ?? [];
        if ($edges === []) {
            return null;
        }

        if ($sourceHandle !== null) {
            foreach ($edges as $edge) {
                $handle = (string) ($edge['sourceHandle'] ?? 'default');
                if ($handle === $sourceHandle) {
                    return (string) ($edge['target'] ?? '');
                }
            }
        }

        foreach ($edges as $edge) {
            $handle = (string) ($edge['sourceHandle'] ?? 'default');
            if ($handle === '' || $handle === 'default') {
                return (string) ($edge['target'] ?? '');
            }
        }

        return (string) ($edges[0]['target'] ?? '');
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return array{version: int, nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public static function linearToGraph(array $steps): array
    {
        $nodes = [
            [
                'id' => 'trigger-1',
                'type' => 'omniFlow',
                'position' => ['x' => 80, 'y' => 40],
                'data' => [
                    'nodeType' => 'trigger',
                    'label' => 'Pesan masuk',
                    'config' => [],
                ],
            ],
        ];
        $edges = [];
        $prevId = 'trigger-1';
        $y = 160;

        foreach ($steps as $i => $step) {
            if (! is_array($step)) {
                continue;
            }
            $type = (string) ($step['type'] ?? '');
            if ($type === '' || $type === 'trigger') {
                continue;
            }

            $id = 'node-'.($i + 1);
            $nodes[] = [
                'id' => $id,
                'type' => 'omniFlow',
                'position' => ['x' => 80, 'y' => $y],
                'data' => [
                    'nodeType' => $type,
                    'label' => $type,
                    'config' => is_array($step['config'] ?? null) ? $step['config'] : [],
                ],
            ];

            if ($type === 'condition') {
                $edges[] = [
                    'id' => 'e-'.$prevId.'-'.$id.'-true',
                    'source' => $prevId,
                    'target' => $id,
                    'sourceHandle' => 'default',
                ];
                $prevId = $id;
                $y += 120;
                continue;
            }

            $edges[] = [
                'id' => 'e-'.$prevId.'-'.$id,
                'source' => $prevId,
                'target' => $id,
                'sourceHandle' => 'default',
            ];
            $prevId = $id;
            $y += 120;
        }

        return [
            'version' => self::VERSION_GRAPH,
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * Linearize graph following default/true path only (for legacy step_count display).
     *
     * @param  array<string, mixed>  $definition
     * @return list<array<string, mixed>>
     */
    public static function graphToLinearSteps(array $definition): array
    {
        $triggerId = self::findTriggerNodeId($definition);
        if ($triggerId === null || $triggerId === '') {
            return [];
        }

        $nodes = self::nodesById($definition);
        $outgoing = self::outgoingEdges($definition);
        $steps = [];
        $currentId = self::nextNodeId($triggerId, $outgoing);
        $visited = [];
        $max = 50;

        while ($currentId && $currentId !== '' && count($visited) < $max) {
            if (isset($visited[$currentId])) {
                break;
            }
            $visited[$currentId] = true;

            $node = $nodes[$currentId] ?? null;
            if (! is_array($node)) {
                break;
            }

            $type = self::nodeType($node);
            if ($type === '' || $type === 'trigger') {
                $currentId = self::nextNodeId($currentId, $outgoing);

                continue;
            }

            $steps[] = [
                'type' => $type,
                'config' => self::nodeConfig($node),
            ];

            if ($type === 'condition') {
                $currentId = self::nextNodeId($currentId, $outgoing, 'true');
            } else {
                $currentId = self::nextNodeId($currentId, $outgoing);
            }
        }

        return $steps;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function normalizeForStorage(array $definition): array
    {
        if (self::isGraph($definition)) {
            return [
                'version' => self::VERSION_GRAPH,
                'nodes' => array_values($definition['nodes'] ?? []),
                'edges' => array_values($definition['edges'] ?? []),
            ];
        }

        $steps = array_values($definition['steps'] ?? []);

        return self::linearToGraph($steps);
    }
}
