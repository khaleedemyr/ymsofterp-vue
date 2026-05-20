<template>
  <VueFlow
    id="omni-flow-canvas"
    v-model:nodes="nodes"
    v-model:edges="edges"
    :node-types="nodeTypes"
    :default-edge-options="defaultEdgeOptions"
    :connection-mode="ConnectionMode.Loose"
    fit-view-on-init
    :min-zoom="0.25"
    :max-zoom="1.5"
    class="h-full w-full bg-slate-100"
    @connect="onConnect"
    @node-click="onNodeClick"
    @pane-click="onPaneClick"
    @drop="onDrop"
    @dragover="onDragOver"
  >
    <Background pattern-color="#cbd5e1" :gap="18" />
    <Controls />
  </VueFlow>
</template>

<script setup>
import { markRaw } from 'vue'
import { VueFlow, useVueFlow, ConnectionMode } from '@vue-flow/core'
import { Background } from '@vue-flow/background'
import { Controls } from '@vue-flow/controls'
import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'

import FlowNode from '@/Components/OmnichannelFlows/FlowNode.vue'
import { createPaletteNode, TRIGGER_NODE_ID } from '@/utils/omniFlowGraph'

const FLOW_ID = 'omni-flow-canvas'

const nodes = defineModel('nodes', { type: Array, required: true })
const edges = defineModel('edges', { type: Array, required: true })

const props = defineProps({
  nodePalette: { type: Array, default: () => [] },
})

const emit = defineEmits(['node-selected'])

const { screenToFlowCoordinate, addNodes } = useVueFlow({ id: FLOW_ID })

const nodeTypes = markRaw({ omniFlow: FlowNode })

const defaultEdgeOptions = {
  type: 'smoothstep',
  animated: true,
  style: { stroke: '#64748b', strokeWidth: 2 },
}

function onConnect(params) {
  const id = `e-${params.source}-${params.target}-${params.sourceHandle || 'default'}`
  if (edges.value.some((e) => e.id === id)) return
  edges.value = [
    ...edges.value,
    {
      id,
      source: params.source,
      target: params.target,
      sourceHandle: params.sourceHandle || 'default',
      type: 'smoothstep',
      animated: true,
    },
  ]
}

function onNodeClick({ node }) {
  emit('node-selected', node)
}

function onPaneClick() {
  emit('node-selected', null)
}

function onDragOver(event) {
  event.preventDefault()
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = 'move'
  }
}

function onDrop(event) {
  event.preventDefault()
  const type = event.dataTransfer?.getData('application/omniflow')
  if (!type || type === 'trigger') return

  const position = screenToFlowCoordinate({
    x: event.clientX,
    y: event.clientY,
  })

  addNodes([createPaletteNode(type, position, props.nodePalette)])
}

function removeNode(nodeId) {
  if (nodeId === TRIGGER_NODE_ID) return
  nodes.value = nodes.value.filter((n) => n.id !== nodeId)
  edges.value = edges.value.filter((e) => e.source !== nodeId && e.target !== nodeId)
  emit('node-selected', null)
}

defineExpose({ removeNode })
</script>
