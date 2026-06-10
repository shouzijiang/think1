import { ref } from 'vue'

const WRONG_FLOAT_POSITIONS = [
  { top: 6, left: 2 },
  { top: 24, left: 18 },
  { top: 42, left: 4 },
  { top: 60, left: 20 },
  { top: 78, left: 6 },
  { top: 10, left: 72 },
  { top: 28, left: 86 },
  { top: 46, left: 70 },
  { top: 64, left: 88 },
  { top: 82, left: 72 }
]

const WRONG_FLOAT_MAX = 10

export function useWrongAnswerFloat() {
  const wrongFloatItems = ref([])

  function pushWrongFloatText(chars) {
    const text = String((chars || []).join('')).trim()
    if (!text) return

    // 已占用的基准位置索引，避免重叠
    const usedIndices = new Set(
      wrongFloatItems.value.map((i) => i._posIdx).filter((v) => v !== undefined)
    )
    const available = WRONG_FLOAT_POSITIONS.map((_, i) => i).filter(
      (i) => !usedIndices.has(i)
    )
    const pool = available.length > 0 ? available : [...usedIndices]
    const idx = pool[Math.floor(Math.random() * pool.length)]
    const pos = WRONG_FLOAT_POSITIONS[idx]
    // 抖动范围扩大到 ±6，同一位置内也有更好的分散度
    const top = Math.max(2, Math.min(92, pos.top + (Math.random() * 12 - 6)))
    const left = Math.max(2, Math.min(88, pos.left + (Math.random() * 12 - 6)))
    const item = {
      id: `${Date.now()}-${Math.random()}`,
      text,
      top,
      left,
      _posIdx: idx,
      duration: 2800 + Math.floor(Math.random() * 1400),
      delay: Math.floor(Math.random() * 300)
    }

    wrongFloatItems.value = [...wrongFloatItems.value, item].slice(
      -WRONG_FLOAT_MAX
    )
  }

  function clearWrongFloatText() {
    wrongFloatItems.value = []
  }

  return {
    wrongFloatItems,
    pushWrongFloatText,
    clearWrongFloatText
  }
}
