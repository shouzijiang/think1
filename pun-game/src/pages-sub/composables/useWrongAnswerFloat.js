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

    const pos =
      WRONG_FLOAT_POSITIONS[
        Math.floor(Math.random() * WRONG_FLOAT_POSITIONS.length)
      ]
    const top = Math.max(2, Math.min(92, pos.top + (Math.random() * 8 - 4)))
    const left = Math.max(2, Math.min(88, pos.left + (Math.random() * 8 - 4)))
    const item = {
      id: `${Date.now()}-${Math.random()}`,
      text,
      top,
      left,
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
