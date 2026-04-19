<template>
  <view class="page">
    <view class="bg-wrap">
      <view class="bg-gradient" />
      <view class="bg-dots" />
      <view class="bg-glow" />
    </view>

    <PunPageNavBar
      :status-bar-height="statusBarHeight"
      :nav-bar-height="navBarHeight"
      :menu-button-height="menuButtonHeight"
      @left-click="back"
    >
      <template #title>
        <text class="nav-title">梗图填词 · 第{{ level }}关</text>
      </template>
    </PunPageNavBar>

    <view class="card">
      <view class="card-inner">
        <image
          v-if="puzzle.imageUrl"
          class="puzzle-img"
          :src="puzzle.imageUrl"
          mode="aspectFill"
        />
        <view v-else-if="loading" class="puzzle-loading">加载中...</view>
        <text class="puzzle-hint">{{ puzzle.hintText }}</text>
        <view class="report-entry" @click="goFeedback">报错</view>
      </view>
    </view>

    <view class="answer-block">
      <view class="answer-row answer-row--divider">
        <view class="answer-left">
          <view class="answer-slots">
            <view
              v-for="i in answerLen"
              :key="i"
              :class="['slot', { 'slot-error': isSlotError(i - 1), 'slot-shake': slotShake }]"
              @click="removeAt(i - 1)"
            >
              {{ answerChars[i - 1] || '' }}
            </view>
          </view>
        </view>
      </view>

      <PunPlayHintShareBar
        :show-hint="true"
        :hint-loading="hintLoading"
        :hint-answer-quota="hintAnswerQuota"
        :hint-locked="loading"
        @hint="onRevealHint"
        @help="help"
        @share-intent="markShareIntent"
      />
    </view>

    <!-- <view class="stuck-tip">
      <text>若通过后没进入下一关，请点击左上角重新游戏</text>
    </view> -->

    <view class="chars-wrap">
      <view
        v-for="(c, idx) in chars"
        :key="idx"
        :class="['char-btn', { 'char-btn-used': isCharUsed(idx) }]"
        @click="pickChar(c, idx)"
      >
        {{ c }}
      </view>
    </view>

    <PunPassSuccessOverlay :show="showSuccess" variant="rich" />
  </view>
</template>

<script setup>
import { ref } from 'vue'
import { onLoad, onShow, onHide, onShareAppMessage, onShareTimeline } from '@dcloudio/uni-app'
import { getLevelPuzzle } from '../../data/levels'
import { api } from '../../utils/api'
import { useNavBar } from '../../composables/useNavBar'
import PunPageNavBar from '../../components/PunPageNavBar.vue'
import PunPlayHintShareBar from '../../components/PunPlayHintShareBar.vue'
import PunPassSuccessOverlay from '../../components/PunPassSuccessOverlay.vue'
import { usePunPassSuccess } from '../../composables/usePunPassSuccess'
import { usePunShareReward } from '../../composables/usePunShareReward'
import { usePunRewardedVideoHint } from '../../composables/usePunRewardedVideoHint'
import { playBgmPlay, stopBgm } from '../../utils/gameAudio'
import {
  punIsSlotError,
  punScheduleWrongAnswerReset,
  punRevealHintWithModal,
  punToastRevealHintAfterError,
} from '../../utils/punPlayShared'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()

const level = ref(1)
const answerLen = ref(3)
const answerChars = ref([])
const chars = ref([])
const puzzle = ref({
  imageUrl: '',
  hintText: '',
})
const loading = ref(true)
const submitting = ref(false)

const feedback = ref([])
const slotShake = ref(false)
const { showSuccess, runPassSuccess } = usePunPassSuccess()
const hintLoading = ref(false)
/** 揭字剩余次数（/pun/level/progress 与 reveal-hint 返回） */
const hintAnswerQuota = ref(0)
const { markShareIntent, withShareReward } = usePunShareReward(hintAnswerQuota)
const { tryWatchAdForHintQuota } = usePunRewardedVideoHint(hintAnswerQuota)
/** 当前已选入答案槽的字在 chars 中的下标，与 answerChars 一一对应 */
const pickedIndices = ref([])

function isCharUsed(idx) {
  return pickedIndices.value.includes(idx)
}

function isSlotError(index) {
  return punIsSlotError(feedback.value, index)
}

onShow(() => {
  playBgmPlay()
})

onHide(() => {
  stopBgm()
})

onLoad((opts) => {
  if (opts && opts.level) {
    level.value = parseInt(opts.level, 10)
  }
  loading.value = true
  getLevelPuzzle(level.value).then((data) => {
    answerLen.value = data.answerLength
    chars.value = data.wordArray.length ? data.wordArray : []
    puzzle.value = {
      imageUrl: data.imageUrl || '',
      hintText: data.hintText || '',
    }
    answerChars.value = []
    pickedIndices.value = []
    loading.value = false
  }).catch(() => {
    loading.value = false
  })
  api.getLevelProgress({ gameTier: 'beginner' }).then((prog) => {
    if (typeof prog.hintAnswerQuota === 'number') {
      hintAnswerQuota.value = prog.hintAnswerQuota
    }
  }).catch(() => {})
})

function pickChar(c, idx) {
  if (answerChars.value.length >= answerLen.value) return
  if (isCharUsed(idx)) return
  answerChars.value = [...answerChars.value, c]
  pickedIndices.value = [...pickedIndices.value, idx]
  if (answerChars.value.length === answerLen.value) {
    checkAnswer()
  }
}

function removeAt(i) {
  const arr = [...answerChars.value]
  const indices = [...pickedIndices.value]
  arr.splice(i, 1)
  indices.splice(i, 1)
  answerChars.value = arr
  pickedIndices.value = indices
}

async function checkAnswer() {
  if (submitting.value) return
  const userAnswer = answerChars.value.slice()
  if (userAnswer.length !== answerLen.value) return
  submitting.value = true
  feedback.value = []
  try {
    const data = await api.submitAnswer(level.value, userAnswer)
    if (data.isCorrect) {
      runPassSuccess({
        durationMs: 1500,
        afterPrepare: () => resolveNextBeginnerLevel(),
        onAfter: (nextLevel) => {
          const targetLevel = Number(nextLevel)
          if (!Number.isFinite(targetLevel)) {
            uni.showToast({ title: '关卡持续更新中,敬请期待,您可以前往首页关卡继续游玩~', icon: 'none' })
            setTimeout(() => {
              uni.reLaunch({ url: '/pages/index/index' })
            }, 1500)
            return
          }
          const maxLevel = 270
          if(targetLevel > maxLevel) {
            uni.showToast({ title: '关卡持续更新中,敬请期待,您可以前往首页关卡继续游玩~', icon: 'none' })
            setTimeout(() => {
              uni.reLaunch({ url: '/pages/index/index' })
            }, 1500)
            return
          }
          // 使用 redirectTo 替换当前页，避免连续 navigateTo 堆满页面栈（微信约 10 层上限）
          uni.redirectTo({ url: `/pages/play/play?level=${targetLevel}` })
        },
      })
      return
    }
    feedback.value = data.feedback || []
    punScheduleWrongAnswerReset(slotShake, () => {
      answerChars.value = []
      pickedIndices.value = []
      feedback.value = []
    })
  } catch (e) {
    uni.showToast({ title: e.message || '提交失败', icon: 'none' })
  } finally {
    submitting.value = false
  }
}

async function resolveNextBeginnerLevel() {
  try {
    const prog = await api.getLevelProgress({ gameTier: 'beginner' })
    const candidate = prog && prog.currentLevel != null ? Number(prog.currentLevel) : NaN
    if (Number.isFinite(candidate) && candidate > 0 && candidate !== level.value) {
      return candidate
    }
    const fallback = level.value + 1
    const totalLevels = prog && prog.totalLevels != null ? Number(prog.totalLevels) : NaN
    if (Number.isFinite(totalLevels) && totalLevels > 0 && fallback > totalLevels) {
      return null
    }
    return fallback
  } catch (_) {
    return level.value + 1
  }
}

async function onRevealHint() {
  if (hintLoading.value || loading.value) return
  let afterRewardVideo = false
  if (hintAnswerQuota.value <= 0) {
    // #ifdef MP-WEIXIN
    afterRewardVideo = true
    hintLoading.value = true
    try {
      const ok = await tryWatchAdForHintQuota()
      if (!ok) return
    } finally {
      hintLoading.value = false
    }
    if (hintAnswerQuota.value <= 0) {
      uni.showToast({ title: '提示次数不足', icon: 'none' })
      return
    }
    // #endif
    // #ifndef MP-WEIXIN
    uni.showToast({ title: '提示次数不足，请前往首页获取更多', icon: 'none' })
    return
    // #endif
  }
  hintLoading.value = true
  try {
    const res = await punRevealHintWithModal({ level: level.value, gameTier: 'beginner' })
    if (typeof res.hintAnswerQuota === 'number') {
      hintAnswerQuota.value = res.hintAnswerQuota
    }
  } catch (e) {
    punToastRevealHintAfterError(e, afterRewardVideo)
  } finally {
    hintLoading.value = false
  }
}

function back() {
  uni.reLaunch({ url: '/pages/index/index' })
}

function goFeedback() {
  const title = `梗图填词 · 第${level.value}关`
  const query = `type=bug&passedLevels=${encodeURIComponent(String(level.value))}&content=${encodeURIComponent(title)}&contentFocus=1`
  uni.navigateTo({ url: `/pages/feedback/feedback?${query}` })
}

// 非微信端点击「求助」时提示（微信端由 open-type="share" 唤起分享）
function help() {
  // #ifndef MP-WEIXIN
  uni.showToast({ title: '分享给好友一起猜～', icon: 'none' })
  // #endif
}

// 微信小程序分享：好友打开后进入当前关卡
onShareAppMessage(() => {
  return withShareReward({
    title: `第${level.value}关求助，快来帮我猜谐音梗！`,
    path: `/pages/play/play?level=${level.value}`,
  })
})

onShareTimeline(() => {
  const img = puzzle.value.imageUrl && String(puzzle.value.imageUrl).startsWith('http')
    ? puzzle.value.imageUrl
    : ''
  return {
    title: `第${level.value}关求助，快来帮我猜谐音梗！`,
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
  @include pt-page-background;
}

.btn-help {
  position: absolute; /* 绝对定位到右侧，因为微信胶囊存在，其实这块区域容易被挡住，看需求可以留着或隐藏 */
  right: 0;
  margin: 0;
  padding: 18rpx 28rpx;
  border: none;
  border-radius: 24rpx;
  background: rgba(255, 255, 255, 0.92);
  color: #5a6d7a;
  font-size: inherit;
  line-height: inherit;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8rpx;
  box-shadow: 0 4rpx 14rpx rgba(169, 201, 238, 0.2);
  border: 2rpx solid rgba(169, 201, 238, 0.55);
}
.btn-help::after {
  border: none;
}
.card {
  position: relative;
  z-index: 2;
  margin-bottom: 32rpx;
  border-radius: 24rpx;
  overflow: hidden;
  box-shadow: 0 8rpx 28rpx rgba(169, 201, 238, 0.18), 0 2rpx 8rpx rgba(0,0,0,0.04);
  background: rgba(255, 255, 255, 0.95);
  border: 2rpx solid rgba(169, 201, 238, 0.45);
}
.card-inner {
  position: relative;
  min-height: 400rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  border-radius: 24rpx;
}
.report-entry {
  position: absolute;
  right: 20rpx;
  bottom: 16rpx;
  z-index: 3;
  padding: 8rpx 20rpx;
  border-radius: 999rpx;
  font-size: 24rpx;
  color: #7d8fa0;
  background: rgba(255, 255, 255, 0.92);
  border: 2rpx solid rgba(169, 201, 238, 0.55);
}
.puzzle-img {
  width: 110%;
  height: 750rpx;
  border-radius: 16rpx;
}
.puzzle-loading {
  height: 420rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28rpx;
  color: #8eadcf;
}
.puzzle-hint {
  margin-top: 24rpx;
  padding: 0 24rpx 28rpx;
  font-size: 32rpx;
  color: #5a6d7a;
  font-weight: 500;
  text-align: center;
}

/* 答题区 + 底部操作条：与 playMid / playXhs 同一结构 */
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
}
.answer-row--divider {
  border-bottom: 1rpx solid rgba(160, 190, 220, 0.35);
}
.answer-left {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.stuck-tip {
  position: relative;
  z-index: 2;
  text-align: center;
  font-size: 24rpx;
  color: #8eadcf;
  margin-top: -16rpx;
  margin-bottom: 32rpx;
}
.answer-slots {
  display: flex;
  flex-direction: row;
  justify-content: center;
  align-items: center;
  gap: 18rpx;
}

.answer-btn-placeholder {
  width: 100%;
  min-height: 86rpx;
  border-radius: 20rpx;
  background: rgba(255, 255, 255, 0.15);
  border: 2rpx dashed rgba(180, 160, 140, 0.25);
}
.slot {
  width: 76rpx;
  height: 76rpx;
  border: 2rpx dashed rgba(169, 201, 238, 0.75);
  border-radius: 20rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36rpx;
  font-weight: 600;
  color: #5a6d7a;
  background: rgba(255, 255, 255, 0.9);
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
@keyframes slot-shake {
  0%, 100% { transform: translateX(0); }
  20% { transform: translateX(-8rpx); }
  40% { transform: translateX(8rpx); }
  60% { transform: translateX(-6rpx); }
  80% { transform: translateX(6rpx); }
}

.chars-wrap {
  position: relative;
  z-index: 2;
  display: flex;
  flex-wrap: wrap;
  gap: 18rpx;
  justify-content: center;
}
.char-btn {
  width: 88rpx;
  height: 88rpx;
  border-radius: 50%;
  background: linear-gradient(145deg, #a8e6a2 0%, #91d58b 55%, #7ec876 100%);
  color: #fff;
  font-size: 36rpx;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 6rpx 20rpx rgba(111, 184, 104, 0.35), inset 0 2rpx 0 rgba(255,255,255,0.35);
  border: 2rpx solid rgba(255, 255, 255, 0.35);
}
.char-btn:active {
  transform: scale(0.96);
}
.char-btn-used {
  background: rgba(234, 246, 249, 0.85);
  color: #8eadcf;
  box-shadow: none;
  border: 2rpx solid rgba(169, 201, 238, 0.45);
}
</style>
