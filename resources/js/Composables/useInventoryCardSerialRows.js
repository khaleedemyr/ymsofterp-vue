import { ref } from 'vue';

export function useInventoryCardSerialRows() {
  const expandedGroups = ref([]);

  function groupRowKey(card) {
    if (!card?.is_grouped) return '';
    return String(card.group_key || card.id || '');
  }

  function isGroupExpanded(card) {
    const key = groupRowKey(card);
    return key !== '' && expandedGroups.value.includes(key);
  }

  function toggleGroup(card, event) {
    if (event) {
      event.stopPropagation();
    }
    const key = groupRowKey(card);
    if (!key) return;

    const index = expandedGroups.value.indexOf(key);
    if (index > -1) {
      expandedGroups.value.splice(index, 1);
    } else {
      expandedGroups.value.push(key);
    }
  }

  function serialLines(card) {
    if (!card?.is_grouped) return [];
    return Array.isArray(card.serial_lines) ? card.serial_lines : [];
  }

  return {
    expandedGroups,
    groupRowKey,
    isGroupExpanded,
    toggleGroup,
    serialLines,
  };
}
