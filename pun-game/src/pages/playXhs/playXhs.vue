<template>
  <view class="page page--xhs">
    <view class="bg-wrap">
      <view class="bg-gradient" />
      <view class="bg-dots" />
      <view class="bg-glow" />
    </view>

    <view :style="{ height: statusBarHeight + 'px' }"></view>

    <view class="nav-bar" :style="{ height: navBarHeight + 'px' }">
      <view class="nav-btn" @click="back" :style="{ width: menuButtonHeight + 'px', height: menuButtonHeight + 'px' }">
        <text class="nav-icon">🏠</text>
      </view>
      <view class="nav-center">
        <text class="nav-title">小红书专辑 · 第{{ level }}关</text>
      </view>
    </view>

    <view class="card card--xhs">
      <view class="keyword-tab keyword-tab--empty">
        <text class="keyword-tab-text">小红书专辑</text>
      </view>
      <view class="author-tag" v-if="puzzle.author">
        <text class="author-text">作者：{{ puzzle.author }}</text>
      </view>
      <view class="card-inner">
        <image v-if="puzzle.imageUrlTop" class="main-img" :src="puzzle.imageUrlTop" mode="aspectFill" />
        <view v-else-if="loading" class="img-placeholder">加载中...</view>
        <view v-else class="img-placeholder">暂无配图</view>
      </view>
    </view>

    <view class="answer-block">
      <view class="answer-row">
        <view class="answer-left">
          <input
            ref="xhsInputRef"
            class="answer-input-cover"
            type="text"
            :value="answerInputValue"
            @input="onAnswerInput"
            confirm-type="done"
            @focus="onInputFocus"
            @blur="onInputBlur"
            @click.stop="focusInput()"
          />
          <view class="answer-slots">
            <view
              v-for="i in answerLen"
              :key="i"
              :class="['slot', { 'slot-error': isSlotError(i - 1), 'slot-shake': slotShake }]"
            >
              <text v-if="answerChars[i - 1]" class="slot-char">{{ answerChars[i - 1] }}</text>
              <view v-else-if="caretIndex === i - 1" class="slot-caret" />
            </view>
          </view>
        </view>
      </view>

      <view class="mid-action-bar">
        <view class="mid-action-inner">
          <button
            class="mid-act-btn mid-act-btn--hint"
            :class="{ 'mid-act-btn--cooldown': hintCooldownLeft > 0 }"
            :loading="hintLoading"
            :disabled="hintLoading || hintCooldownLeft > 0"
            hover-class="mid-act-btn--hover"
            @click="onRevealHint"
          >
            <text class="mid-act-ico">💡</text>
            <text class="mid-act-txt">{{ hintCooldownLeft > 0 ? '答案 ' + hintCooldownLeft + 's' : '答案' }}</text>
          </button>
          <button class="mid-act-btn mid-act-btn--share" open-type="share" hover-class="mid-act-btn--hover">
            <text class="mid-act-ico">💬</text>
            <text class="mid-act-txt">求助</text>
          </button>
        </view>
      </view>
    </view>

    <view v-if="showSuccess" class="success-overlay">
      <view class="success-content">
        <view class="success-icon-wrap">
          <text class="success-icon">🎉</text>
        </view>
        <text class="success-title">回答正确~</text>
      </view>
    </view>
  </view>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import { onLoad, onShow, onHide, onUnload, onShareAppMessage, onShareTimeline } from '@dcloudio/uni-app'
import { getXhsLevelPuzzle, getXhsNextLevel, loadXhsLevelList, pickXhsLevelFromProgress, prefetchNextXhsLevelImage } from '../../data/levels'
import { api } from '../../utils/api'
import { useNavBar } from '../../composables/useNavBar'
import { playBgmPlay, stopBgm, playCongratsOnce, playErrorOnce } from '../../utils/gameAudio'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()

const level = ref(1)
const answerLen = ref(3)
const answerChars = ref([])
const puzzle = ref({
  imageUrlTop: '',
  keywordHint: '',
  author: '',
})
const loading = ref(true)
const submitting = ref(false)
const hintLoading = ref(false)
const hintCooldownLeft = ref(0)
let hintCooldownTimer = null
const HINT_COOLDOWN_SEC = 20

function clearHintCooldown() {
  if (hintCooldownTimer) {
    clearInterval(hintCooldownTimer)
    hintCooldownTimer = null
  }
  hintCooldownLeft.value = 0
}

function startHintCooldown() {
  clearHintCooldown()
  hintCooldownLeft.value = HINT_COOLDOWN_SEC
  hintCooldownTimer = setInterval(() => {
    hintCooldownLeft.value--
    if (hintCooldownLeft.value <= 0) {
      clearHintCooldown()
    }
  }, 1000)
}

const feedback = ref([])
const slotShake = ref(false)
const showSuccess = ref(false)
const answerInputValue = ref('')
const xhsInputRef = ref(null)
const inputFocused = ref(false)

const caretIndex = computed(() => {
  if (!inputFocused.value) return -1
  const n = answerChars.value.length
  if (n >= answerLen.value) return -1
  return n
})

function isSlotError(index) {
  const fb = feedback.value[index]
  return fb && fb.isCorrect === false
}

function onInputFocus() {
  inputFocused.value = true
}

function onInputBlur() {
  inputFocused.value = false
}

function focusInput() {
  nextTick(() => {
    const el = xhsInputRef.value
    if (el && typeof el.focus === 'function') el.focus()
  })
}

function onAnswerInput(e) {
  const raw = (e.detail && e.detail.value) || ''
  answerInputValue.value = raw
  const hasHan = /[\u4e00-\u9fff]/.test(raw)
  if (!hasHan) {
    answerChars.value = []
    return
  }
  const parts = Array.from(raw).slice(0, answerLen.value)
  answerChars.value = parts
  if (parts.length === answerLen.value) checkAnswer()
}

async function checkAnswer() {
  if (submitting.value) return
  const userAnswer = answerChars.value.slice()
  if (userAnswer.length !== answerLen.value) return
  submitting.value = true
  feedback.value = []

  try {
    const data = await api.submitAnswer(level.value, userAnswer, { gameTier: 'xhs' })
    if (data.isCorrect) {
      showSuccess.value = true
      playCongratsOnce()
      const nextLevel = await getXhsNextLevel(level.value)
      setTimeout(() => {
        showSuccess.value = false
        if (nextLevel == null) {
          uni.showToast({ title: '专辑持续更新中，敬请期待~', icon: 'none' })
          setTimeout(() => {
            uni.reLaunch({ url: '/pages/index/index' })
          }, 1200)
          return
        }
        uni.redirectTo({ url: `/pages/playXhs/playXhs?level=${nextLevel}` })
      }, 1200)
      return
    }
    feedback.value = data.feedback || []
    playErrorOnce()
    slotShake.value = true
    uni.showToast({ title: '再想想～', icon: 'none' })
    setTimeout(() => {
      slotShake.value = false
      setTimeout(() => {
        answerChars.value = []
        answerInputValue.value = ''
        feedback.value = []
      }, 200)
    }, 600)
  } catch (e) {
    uni.showToast({ title: e.message || '提交失败', icon: 'none' })
  } finally {
    submitting.value = false
  }
}

async function onRevealHint() {
  if (hintLoading.value || hintCooldownLeft.value > 0 || loading.value) return
  hintLoading.value = true
  try {
    const data = await api.revealHint({ level: level.value, gameTier: 'xhs' })
    uni.showModal({
      title: data.isComplete ? '已全部提示' : `提示 (${data.step}/${data.maxSteps})`,
      content: data.hintText,
      showCancel: false,
    })
  } catch (e) {
    uni.showToast({ title: e.message || '获取失败', icon: 'none' })
  } finally {
    hintLoading.value = false
  }
}

function back() {
  uni.reLaunch({ url: '/pages/index/index' })
}

onShow(() => {
  playBgmPlay()
})

onHide(() => {
  stopBgm()
})

onLoad(async (opts) => {
  let lv = opts && opts.level != null && opts.level !== '' ? parseInt(opts.level, 10) : NaN
  const fromQuery = Number.isFinite(lv) && lv > 0
  if (!fromQuery) {
    loading.value = true
    try {
      const data = await api.getLevelProgress({ gameTier: 'xhs' })
      await loadXhsLevelList()
      const resolved = pickXhsLevelFromProgress(data)
      lv = resolved != null ? resolved : 1
    } catch {
      lv = 1
    }
  }

  level.value = lv
  loading.value = true
  getXhsLevelPuzzle(lv)
    .then((data) => {
      answerLen.value = data.answerLength || 3
      puzzle.value = {
        imageUrlTop: data.imageUrlTop || data.imageUrl || '',
        keywordHint: '',
        author: data.author || '',
      }
      answerChars.value = []
      feedback.value = []
      answerInputValue.value = ''
      loading.value = false
      startHintCooldown()
      prefetchNextXhsLevelImage(lv)
    })
    .catch(() => {
      loading.value = false
    })
})

onUnload(() => {
  clearHintCooldown()
})

onShareAppMessage(() => {
  return {
    title: `小红书专辑·第${level.value}关，快来帮我猜！`,
    path: `/pages/playXhs/playXhs?level=${level.value}`,
  }
})

onShareTimeline(() => {
  const img = puzzle.value.imageUrlTop || ''
  return {
    title: `小红书专辑·第${level.value}关，快来帮我猜！`,
    query: `level=${level.value}`,
    ...(img ? { imageUrl: img } : {}),
  }
})
</script>

<style lang="scss" scoped>
@use '../../styles/page-theme.scss' as *;

.page {
  min-height: 100vh;
  position: relative;
  padding-bottom: 48rpx;
  padding-left: 40rpx;
  padding-right: 40rpx;
  box-sizing: border-box;
  overflow: hidden;
  @include pt-page-background;
}

.card {
  position: relative;
  z-index: 2;
  margin-bottom: 32rpx;
  border-radius: 24rpx;
  overflow: visible;
  box-shadow: 0 12rpx 36rpx rgba(169, 201, 238, 0.22), 0 2rpx 10rpx rgba(0,0,0,0.05);
  background: rgba(255, 255, 255, 0.95);
  border: 2rpx solid rgba(169, 201, 238, 0.55);
}

.keyword-tab {
  position: absolute;
  top: 26rpx;
  right: 18rpx;
  z-index: 4;
  min-height: auto;
  padding: 14rpx 20rpx;
  background: linear-gradient(145deg, #f6b0c3 0%, #ee8fad 100%);
  border-radius: 14rpx;
  box-shadow: 0 10rpx 24rpx rgba(238, 143, 173, 0.28);
}
.keyword-tab-text {
  font-size: 24rpx;
  font-weight: 700;
  color: #fff;
}

.author-tag {
  position: absolute;
  top: 26rpx;
  left: 18rpx;
  z-index: 4;
  padding: 12rpx 16rpx;
  background: rgba(255, 255, 255, 0.88);
  border: 1rpx solid rgba(169, 201, 238, 0.45);
  border-radius: 12rpx;
}
.author-text {
  font-size: 22rpx;
  color: #7d8fa0;
  font-weight: 600;
}

.card-inner {
  min-height: 420rpx;
  padding: 36rpx 28rpx 32rpx;
}

.main-img {
  width: 100%;
  height: 820rpx;
  border-radius: 20rpx;
  background: #f0f4f8;
}
.img-placeholder {
  width: 100%;
  height: 520rpx;
  border-radius: 20rpx;
  background: rgba(240, 244, 248, 0.95);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28rpx;
  color: #8a9aaa;
}

.answer-block {
  position: relative;
  z-index: 2;
  margin-bottom: 32rpx;
  background: rgba(255, 255, 255, 0.94);
  border-radius: 28rpx;
  border: 2rpx solid rgba(180, 200, 230, 0.4);
  box-shadow: 0 8rpx 24rpx rgba(100, 140, 180, 0.09), 0 2rpx 8rpx rgba(0, 0, 0, 0.03);
  overflow: hidden;
}
.answer-row {
  position: relative;
  padding: 28rpx 24rpx 24rpx;
  border-bottom: 1rpx solid rgba(160, 190, 220, 0.35);
}
.answer-left {
  position: relative;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.answer-input-cover {
  position: absolute;
  left: -200%;
  top: 0;
  right: 0;
  bottom: 0;
  width: 300%;
  height: 100%;
  opacity: 0.001;
  z-index: 3;
  font-size: 32rpx;
  color: transparent;
  caret-color: transparent;
  background: transparent;
  border: none;
  padding: 0;
}

.answer-slots {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: row;
  justify-content: center;
  align-items: center;
  gap: 18rpx;
  flex-wrap: wrap;
}
.slot {
  min-width: 76rpx;
  height: 76rpx;
  padding: 0 8rpx;
  border: 2rpx dashed rgba(169, 201, 238, 0.75);
  border-radius: 20rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36rpx;
  font-weight: 600;
  color: #5a6d7a;
  background: rgba(255, 255, 255, 0.9);
  box-sizing: border-box;
}
.slot-error {
  border-color: #c04a38;
  border-style: solid;
  color: #c04a38;
  background: rgba(255, 220, 210, 0.5);
}
.slot-shake {
  animation: slot-shake 0.4s ease-in-out;
}

.mid-action-bar {
  display: flex;
  flex-direction: row;
  justify-content: center;
  padding: 16rpx 28rpx 20rpx;
  background: rgba(250, 252, 255, 0.92);
}
.mid-action-inner {
  display: flex;
  flex-direction: row;
  align-items: stretch;
  gap: 18rpx;
  width: 100%;
  max-width: 520rpx;
}
.mid-act-btn {
  flex: 1;
  min-width: 0;
  margin: 0;
  min-height: 76rpx;
  padding: 0 18rpx;
  border: none;
  border-radius: 38rpx;
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
  gap: 8rpx;
}
.mid-act-btn::after {
  border: none;
}
.mid-act-btn--hint,
.mid-act-btn--share {
  color: #5a6d7a;
  background: linear-gradient(180deg, #ffffff 0%, #f0faf9 100%);
  border: 1rpx solid rgba(169, 201, 238, 0.55);
  box-shadow: 0 4rpx 14rpx rgba(169, 201, 238, 0.12);
}
.mid-act-btn--hover {
  opacity: 0.9;
}
.mid-act-btn--cooldown {
  opacity: 0.65;
}
.mid-act-ico {
  font-size: 26rpx;
}
.mid-act-txt {
  font-size: 28rpx;
  font-weight: 600;
}

.slot-caret {
  width: 10rpx;
  height: 46rpx;
  border-radius: 8rpx;
  background: rgba(80, 120, 200, 0.55);
  animation: caret-blink 1s steps(2, start) infinite;
}

@keyframes caret-blink {
  0%, 49% { opacity: 1; }
  50%, 100% { opacity: 0; }
}
@keyframes slot-shake {
  0%, 100% { transform: translateX(0); }
  20% { transform: translateX(-8rpx); }
  40% { transform: translateX(8rpx); }
  60% { transform: translateX(-6rpx); }
  80% { transform: translateX(6rpx); }
}

.success-overlay {
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
}
.success-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: linear-gradient(145deg, #ffffff 0%, #fdfbfb 100%);
  padding: 60rpx 80rpx;
  border-radius: 40rpx;
}
.success-icon-wrap {
  width: 160rpx;
  height: 160rpx;
  background: #f0fdf4;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 32rpx;
}
.success-icon {
  font-size: 80rpx;
}
.success-title {
  font-size: 48rpx;
  font-weight: 900;
  color: #166534;
}
</style>
