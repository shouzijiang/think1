/**
 * Album category configuration
 * Auto-generated from scripts/gen_category_json.php
 * Update when issue3.json answerTypes change
 *
 * Each subType can have:
 *   - type: display name (used as filterType key, comma-joined answerTypes)
 *   - answerTypes: array of answerType values to filter by
 *   - count: total entries across all answerTypes
 */
export const ALBUM_CATEGORIES = [
  {
    slug: 'character',
    label: '人物篇',
    icon: '🧑‍🎨',
    color: '#6C5CE7',
    subTypes: [
      { type: '历史人物', answerTypes: ['历史人物'], count: 59 },
      { type: '漫威影视角色', answerTypes: ['漫威影视角色'], count: 15 },
      { type: '世界著名音乐家', answerTypes: ['世界著名音乐家'], count: 8 },
      { type: '明星', answerTypes: ['明星'], count: 8 },
      { type: '科学家', answerTypes: ['科学家'], count: 7 },
      { type: '动漫角色', answerTypes: ['动漫角色'], count: 2 },
    ],
  },
  {
    slug: 'city',
    label: '城市篇',
    icon: '🏙️',
    color: '#48DBFB',
    subTypes: [
      { type: '城市', answerTypes: ['城市'], count: 49 },
    ],
  },
  {
    slug: 'landscape',
    label: '风景名胜篇',
    icon: '🏔️',
    color: '#0ABDE3',
    subTypes: [
      { type: '风景名胜', answerTypes: ['风景名胜'], count: 38 },
    ],
  },
  {
    slug: 'food',
    label: '食物篇',
    icon: '🍜',
    color: '#FF6B6B',
    subTypes: [
      { type: '美食', answerTypes: ['美食', '食物', '菜名', '菜品'], count: 152 },
      { type: '小吃', answerTypes: ['小吃'], count: 32 },
      { type: '家常菜', answerTypes: ['家常下饭菜'], count: 8 },
      { type: '茶饮', answerTypes: ['茶', '饮品'], count: 8 },
      { type: '调料', answerTypes: ['调料'], count: 6 },
    ],
  },
  {
    slug: 'fruit',
    label: '水果篇',
    icon: '🍎',
    color: '#F8A5C2',
    subTypes: [
      { type: '水果', answerTypes: ['水果'], count: 40 },
    ],
  },
  {
    slug: 'dessert',
    label: '甜品篇',
    icon: '🍰',
    color: '#FD79A8',
    subTypes: [
      { type: '甜品', answerTypes: ['甜品'], count: 9 },
      { type: '点心', answerTypes: ['点心'], count: 7 },
    ],
  },
  {
    slug: 'idiom',
    label: '成语篇',
    icon: '📚',
    color: '#FECA57',
    subTypes: [
      { type: '成语', answerTypes: ['成语'], count: 84 },
    ],
  },
  {
    slug: 'plant',
    label: '植物篇',
    icon: '🌿',
    color: '#26DE81',
    subTypes: [
      { type: '植物', answerTypes: ['植物'], count: 36 },
      { type: '中药材', answerTypes: ['中药材'], count: 8 },
    ],
  },
  {
    slug: 'christmas',
    label: '圣诞节篇',
    icon: '🎄',
    color: '#E74C3C',
    subTypes: [
      { type: '圣诞节', answerTypes: ['圣诞节'], count: 6 },
    ],
  },
  {
    slug: 'newyear',
    label: '新年篇',
    icon: '🧧',
    color: '#E17055',
    subTypes: [
      { type: '新年快乐', answerTypes: ['新年快乐'], count: 6 },
    ],
  },
  {
    slug: 'zodiac',
    label: '生肖篇',
    icon: '🐲',
    color: '#F39C12',
    subTypes: [
      { type: '鼠', answerTypes: ['鼠'], count: 6 },
      { type: '牛', answerTypes: ['牛'], count: 6 },
      { type: '虎', answerTypes: ['虎'], count: 12 },
      { type: '兔', answerTypes: ['兔'], count: 6 },
      { type: '马', answerTypes: ['马'], count: 18 },
      { type: '羊', answerTypes: ['羊'], count: 6 },
    ],
  },
]

/**
 * answerType → category slug lookup
 * Each individual answerType maps to its parent category slug
 */
export const ANSWER_TYPE_CATEGORY_MAP = {}
for (const cat of ALBUM_CATEGORIES) {
  for (const sub of cat.subTypes) {
    for (const at of sub.answerTypes) {
      ANSWER_TYPE_CATEGORY_MAP[at] = cat.slug
    }
  }
}
