<template>
  <view class="page" @touchstart="onTouchStart" @touchend="onTouchEnd">
    <view class="bg-wrap">
      <view class="bg-gradient" />
      <view class="bg-dots" />
      <view class="bg-glow" />
    </view>

    <PunPageNavBar
      :status-bar-height="statusBarHeight"
      :nav-bar-height="navBarHeight"
      :menu-button-height="menuButtonHeight"
      title="我的关卡"
      @left-click="back"
    />

    <view class="tabs-wrap">
      <view class="tabs">
        <view :class="['tab', { active: tier === 'mid' }]" @click="switchTier('mid')">
          经典
        </view>
        <view :class="['tab', { active: tier === 'beginner' }]" @click="switchTier('beginner')">
          梗图填词
        </view>
        <view :class="['tab', { active: tier === 'xhs' }]" @click="switchTier('xhs')">
          小红书
        </view>
      </view>
    </view>

    <view v-if="loading" class="levels-loading">
      <text class="levels-loading-text">加载中…</text>
    </view>
    <block v-else>
      <view class="grid-wrap">
        <view v-for="cell in displayCells" :key="cell.levelId" class="cell-wrap">
          <view
            :class="['cell', cell.cellClass]"
            @click="onLevelClick(cell)"
          >
            <text v-if="cell.completed" class="cell-star">⭐</text>
            <text class="cell-num" v-if="cell.levelId !== 0">{{ cell.levelId }}</text>
            <text class="cell-num cell-num-2" v-else>新手<text style="display: block;">引导</text></text>
            <view v-if="cell.completed" class="cell-done">✓</view>
            <view v-else-if="cell.locked" class="cell-lock">🔒</view>
          </view>
        </view>
      </view>

      <view class="pager">
        <view
          class="pager-btn"
          hover-class="pager-btn--hover"
          :hover-start-time="20"
          :hover-stay-time="100"
          @click="prevPage"
        >
          <text class="pager-icon">‹</text>
        </view>
        <text class="pager-text">{{ currentPage }}/{{ totalPages }}</text>
        <view
          class="pager-btn"
          hover-class="pager-btn--hover"
          :hover-start-time="20"
          :hover-stay-time="100"
          @click="nextPage"
        >
          <text class="pager-icon">›</text>
        </view>
      </view>
    </block>
  </view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { LEVELS_PER_PAGE, getCurrentLevel, getPassedLevels, loadMidLevelList, loadXhsLevelList } from '../../data/levels'
import { api } from '../../utils/api'
import { useNavBar } from '../../composables/useNavBar'
import PunPageNavBar from '../../components/PunPageNavBar.vue'
import { useWechatPageShare } from '../composables/useWechatPageShare'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()

const shareRewardQuotaRef = ref(0)
useWechatPageShare('我的关卡 · 谐音梗猜一猜', shareRewardQuotaRef)

// tier: beginner=初级；mid=中级（issue2.json 顺序）；xhs=小红书（issue3.json 顺序）
const tier = ref('mid')

const totalLevels = ref(0)
const perPage = LEVELS_PER_PAGE
const currentPage = ref(1)
const passedSet = ref(new Set(getPassedLevels()))
const currentLevel = ref(getCurrentLevel())

// 中级完整列表（issue2.json 顺序）
const midLevelList = ref([])
// 小红书完整列表（issue3.json 顺序）
const xhsLevelList = ref([])
const loading = ref(false)
const touchStartX = ref(0)
const touchStartY = ref(0)

const totalPages = computed(() => Math.ceil(totalLevels.value / perPage) || 1)
const currentStart = computed(() => (currentPage.value - 1) * perPage) // 0-based

const displayLevelIds = computed(() => {
  if (!totalLevels.value) return []
  const start = currentStart.value
  const end = Math.min(start + perPage, totalLevels.value)
  if (tier.value === 'beginner') {
    const arr = []
    for (let id = start + 1; id <= end; id++) arr.push(id)
    return arr
  }
  // mid/xhs：按题库顺序切片
  const list = tier.value === 'xhs'
    ? (Array.isArray(xhsLevelList.value) ? xhsLevelList.value : [])
    : (Array.isArray(midLevelList.value) ? midLevelList.value : [])
  return list.slice(start, end)
})

/** 当前分栏下的完整关卡顺序（与解锁判定一致，只算一次） */
const orderedLevels = computed(() => {
  if (tier.value === 'xhs') {
    return Array.isArray(xhsLevelList.value) ? xhsLevelList.value : []
  }
  if (tier.value === 'mid') {
    return Array.isArray(midLevelList.value) ? midLevelList.value : []
  }
  const arr = []
  const t = totalLevels.value
  for (let id = 1; id <= t; id++) arr.push(id)
  return arr
})

/**
 * 关卡顺序索引 + 通关前沿（一次遍历 orderedLevels，减少小程序端重复计算）
 */
const levelUnlockContext = computed(() => {
  const ord = orderedLevels.value
  const passed = passedSet.value
  const orderIndex = new Map()
  let frontier = -1
  for (let i = 0; i < ord.length; i++) {
    const id = ord[i]
    orderIndex.set(id, i)
    if (passed.has(id)) frontier = i
  }
  return { orderIndex, frontier }
})

/**
 * 当前页每个格子的展示状态（避免模板里对每个格子重复 indexOf / 全表扫描）
 */
const displayCells = computed(() => {
  const ids = displayLevelIds.value
  const cur = currentLevel.value
  const passed = passedSet.value
  const { orderIndex: ordIdx, frontier: fi } = levelUnlockContext.value

  return ids.map((levelId) => {
    const completed = passed.has(levelId)
    const current = cur != null && levelId === cur
    let unlocked = completed || current
    if (!unlocked && ordIdx.size) {
      const levelIndex = ordIdx.get(levelId)
      if (levelIndex !== undefined) {
        unlocked = levelIndex <= fi + 1
      }
    }
    const locked = !unlocked
    let cellClass = 'locked'
    if (completed) cellClass = 'done'
    else if (current) cellClass = 'current'
    else if (unlocked) cellClass = 'unlocked'
    return { levelId, completed, locked, cellClass }
  })
})

function onLevelClick(cell) {
  if (cell.locked) {
    uni.showToast({ title: '请先通过上一关', icon: 'none' })
    return
  }
  const levelId = cell.levelId
  if (tier.value === 'mid') {
    uni.navigateTo({ url: `/pages-sub/playMid/playMid?level=${levelId}` })
  } else if (tier.value === 'xhs') {
    uni.navigateTo({ url: `/pages-sub/playXhs/playXhs?level=${levelId}` })
  } else {
    // 初级无 issue/0.json；「新手引导」格 levelId=0 时进入第1关
    const playLevel = levelId === 0 ? 1 : levelId
    uni.navigateTo({ url: `/pages-sub/play/play?level=${playLevel}` })
  }
}

function prevPage() {
  if (currentPage.value <= 1) return
  currentPage.value--
}
function nextPage() {
  if (currentPage.value >= totalPages.value) return
  currentPage.value++
}

function loadProgress() {
  loading.value = true
  if (tier.value === 'mid') {
    // 中级：仅使用统一字段（totalLevels/passedLevels/currentLevel）
    api.getLevelProgress({ gameTier: 'mid' })
      .then(async (data) => {
        const list = await loadMidLevelList()
        midLevelList.value = list

        const totalRaw = data && data.totalLevels != null ? Number(data.totalLevels) : list.length
        const normalizedPassed = data && Array.isArray(data.passedLevels)
          ? data.passedLevels
            .map((x) => Number(x))
            .filter((x) => Number.isFinite(x) && list.includes(x))
          : []
        const passed = new Set(normalizedPassed)

        let cur = data && data.currentLevel != null ? Number(data.currentLevel) : null
        if (!(cur != null && Number.isFinite(cur) && list.includes(cur))) {
          cur = list.find((id) => !passed.has(id)) ?? null
        }

        totalLevels.value = Number.isFinite(totalRaw) && totalRaw > 0 ? totalRaw : list.length
        passedSet.value = passed
        currentLevel.value = cur
      })
      .catch(() => {
        midLevelList.value = []
        totalLevels.value = 0
        passedSet.value = new Set()
        currentLevel.value = null
      })
      .finally(() => {
        loading.value = false
      })
    return
  }
  if (tier.value === 'xhs') {
    api.getLevelProgress({ gameTier: 'xhs' })
      .then(async (data) => {
        const list = await loadXhsLevelList()
        xhsLevelList.value = list

        const totalRaw = data && data.totalLevels != null ? Number(data.totalLevels) : list.length
        const normalizedPassed = data && Array.isArray(data.passedLevels)
          ? data.passedLevels
            .map((x) => Number(x))
            .filter((x) => Number.isFinite(x) && list.includes(x))
          : []
        const passed = new Set(normalizedPassed)

        let cur = data && data.currentLevel != null ? Number(data.currentLevel) : null
        if (!(cur != null && Number.isFinite(cur) && list.includes(cur))) {
          cur = list.find((id) => !passed.has(id)) ?? null
        }

        totalLevels.value = Number.isFinite(totalRaw) && totalRaw > 0 ? totalRaw : list.length
        passedSet.value = passed
        currentLevel.value = cur
      })
      .catch(() => {
        xhsLevelList.value = []
        totalLevels.value = 0
        passedSet.value = new Set()
        currentLevel.value = null
      })
      .finally(() => {
        loading.value = false
      })
    return
  }

  // 初级：保持兼容老接口（currentLevel/passedLevels/totalLevels）
  api.getLevelProgress({ gameTier: 'beginner' })
    .then((data) => {
      const rawCur = data.currentLevel != null ? Number(data.currentLevel) : getCurrentLevel()
      currentLevel.value = Number.isFinite(rawCur) && rawCur >= 1 ? rawCur : 1
      passedSet.value = new Set(Array.isArray(data.passedLevels) ? data.passedLevels : getPassedLevels())
      if (data.totalLevels != null) totalLevels.value = data.totalLevels
    })
    .catch(() => {
      passedSet.value = new Set(getPassedLevels())
      currentLevel.value = getCurrentLevel()
      totalLevels.value = 0
    })
    .finally(() => {
      loading.value = false
    })
}

onShow(() => loadProgress())

function switchTier(next) {
  if (tier.value === next) return
  tier.value = next
  currentPage.value = 1
  loadProgress()
}

function getTierOrder() {
  return ['mid', 'beginner', 'xhs']
}

function switchTierByOffset(offset) {
  const order = getTierOrder()
  const currentIdx = order.indexOf(tier.value)
  if (currentIdx < 0) return
  const nextIdx = currentIdx + offset
  if (nextIdx < 0 || nextIdx >= order.length) return
  switchTier(order[nextIdx])
}

function onTouchStart(e) {
  const touch = e && e.touches && e.touches[0]
  if (!touch) return
  touchStartX.value = touch.clientX
  touchStartY.value = touch.clientY
}

function onTouchEnd(e) {
  const touch = e && e.changedTouches && e.changedTouches[0]
  if (!touch) return
  const deltaX = touch.clientX - touchStartX.value
  const deltaY = touch.clientY - touchStartY.value
  const absX = Math.abs(deltaX)
  const absY = Math.abs(deltaY)

  // 仅处理明确的水平滑动，避免与上下滚动冲突
  if (absX < 60 || absX <= absY) return

  // 左滑到下一个榜单，右滑到上一个榜单
  if (deltaX < 0) {
    switchTierByOffset(1)
  } else {
    switchTierByOffset(-1)
  }
}

function back() {
  uni.reLaunch({ url: '/pages/index/index' })
}
</script>

<style lang="scss" scoped>
@use '../../styles/page-theme.scss' as *;

.page {
  min-height: 100vh;
  position: relative;
  padding-bottom: 160rpx;
  padding-left: 40rpx;
  padding-right: 40rpx;
  box-sizing: border-box;
  @include pt-page-background;

  .tabs-wrap {
    position: relative;
    z-index: 2;
    display: flex;
    justify-content: center;
    margin-bottom: 30rpx;

    .tabs {
      display: flex;
      gap: 18rpx;
      padding: 10rpx;
      border-radius: 40rpx;
      background: rgba(255, 255, 255, 0.75);
      box-shadow: 0 6rpx 20rpx rgba(169, 201, 238, 0.14);
      border: 2rpx solid rgba(169, 201, 238, 0.45);

      .tab {
        padding: 14rpx 44rpx;
        border-radius: 32rpx;
        font-size: 28rpx;
        font-weight: 600;
        color: #8eadcf;
        transition: all 0.2s ease;

        &.active {
          color: #ffffff;
          background: linear-gradient(145deg, #a8e6a2 0%, #91d58b 100%);
          box-shadow: 0 10rpx 28rpx rgba(111, 184, 104, 0.25);
        }
      }
    }
  }

  .levels-loading {
    position: relative;
    z-index: 2;
    min-height: 45vh;
    display: flex;
    align-items: center;
    justify-content: center;

    .levels-loading-text {
      font-size: 28rpx;
      color: #8eadcf;
    }
  }

  .grid-wrap {
    position: relative;
    z-index: 2;
    display: flex;
    flex-wrap: wrap;
    gap: 18rpx;
    justify-content: space-between;

    .cell-wrap {
      width: calc((100% - 4 * 18rpx) / 5);
      aspect-ratio: 1;
    }

    .cell {
      width: 100%;
      height: 100%;
      border-radius: 20rpx;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      position: relative;
      box-shadow: 0 4rpx 16rpx rgba(169, 201, 238, 0.12);
      border: 2rpx solid rgba(169, 201, 238, 0.4);

      &.done {
        background: linear-gradient(145deg, #a8e6a2 0%, #7ec876 100%);
        color: #fff;
        border-color: transparent;
        box-shadow: 0 6rpx 20rpx rgba(111, 184, 104, 0.35), inset 0 2rpx 0 rgba(255, 255, 255, 0.25);

        .cell-star {
          position: absolute;
          top: 8rpx;
          left: 12rpx;
          font-size: 26rpx;
        }

        .cell-done {
          position: absolute;
          bottom: 10rpx;
          width: 36rpx;
          height: 36rpx;
          border-radius: 50%;
          background: rgba(255, 255, 255, 0.95);
          color: #2d6b3a;
          font-size: 22rpx;
          font-weight: bold;
          display: flex;
          align-items: center;
          justify-content: center;
        }
      }

      &.current {
        background: linear-gradient(145deg, #b8e8b2 0%, #91d58b 100%);
        color: #fff;
        border-color: transparent;
        box-shadow: 0 8rpx 24rpx rgba(111, 184, 104, 0.38), inset 0 2rpx 0 rgba(255, 255, 255, 0.28);
      }

      &.unlocked {
        background: rgba(255, 255, 255, 0.92);
        color: #5a6d7a;
        border-color: rgba(169, 201, 238, 0.55);
        box-shadow: 0 4rpx 16rpx rgba(169, 201, 238, 0.12);
      }

      &.locked {
        background: rgba(255, 255, 255, 0.72);
        color: #b8c4d0;
        border-color: rgba(169, 201, 238, 0.35);

        .cell-num {
          color: #8eadcf;
        }

        .cell-lock {
          position: absolute;
          bottom: 10rpx;
          font-size: 24rpx;
        }
      }

      .cell-num {
        font-size: 30rpx;
        font-weight: 700;
      }
    }
  }

  .pager {
    position: fixed;
    bottom: 56rpx;
    left: 50%;
    transform: translateX(-50%);
    z-index: 3;
    display: flex;
    align-items: center;
    gap: 36rpx;
    padding: 16rpx 32rpx;
    background: rgba(255, 255, 255, 0.92);
    border-radius: 48rpx;
    box-shadow: 0 8rpx 28rpx rgba(169, 201, 238, 0.18);
    border: 2rpx solid rgba(169, 201, 238, 0.45);

    .pager-btn {
      width: 64rpx;
      height: 64rpx;
      border-radius: 50%;
      background: linear-gradient(145deg, #a8e6a2 0%, #91d58b 100%);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4rpx 14rpx rgba(111, 184, 104, 0.28);

      &.pager-btn--hover {
        transform: scale(0.95);
      }
    }

    .pager-icon {
      font-size: 38rpx;
      font-weight: bold;
      line-height: 1;
    }

    .pager-text {
      font-size: 28rpx;
      color: #5a6d7a;
      font-weight: 500;
      min-width: 72rpx;
      text-align: center;
    }
  }
}
</style>

