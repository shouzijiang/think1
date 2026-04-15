<template>
  <view class="page page--xhs">
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
        <text class="nav-title">小红书专辑 · 第{{ level }}关</text>
      </template>
    </PunPageNavBar>

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
        <view class="report-entry" @click="goFeedback">报错</view>
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

      <PunPlayHintShareBar
        :hint-loading="hintLoading"
        :hint-answer-quota="hintAnswerQuota"
        :hint-locked="loading"
        @hint="onRevealHint"
        @help="help"
        @share-intent="markShareIntent"
      />
    </view>

    <PunPassSuccessOverlay :show="showSuccess" variant="plain" />
  </view>
</template>

<script setup>
import { ref } from 'vue'
import { onLoad, onShow, onHide, onShareAppMessage, onShareTimeline } from '@dcloudio/uni-app'
import { getXhsLevelPuzzle, getXhsNextLevel, loadXhsLevelList, pickXhsLevelFromProgress, prefetchNextXhsLevelImage } from '../../data/levels'
import { api } from '../../utils/api'
import { useNavBar } from '../../composables/useNavBar'
import PunPageNavBar from '../../components/PunPageNavBar.vue'
import PunPlayHintShareBar from '../../components/PunPlayHintShareBar.vue'
import PunPassSuccessOverlay from '../../components/PunPassSuccessOverlay.vue'
import { usePunPassSuccess } from '../../composables/usePunPassSuccess'
import { usePunShareReward } from '../../composables/usePunShareReward'
import { usePunHanAnswerInput } from '../../composables/usePunHanAnswerInput'
import { playBgmPlay, stopBgm } from '../../utils/gameAudio'
import {
  punIsSlotError,
  punScheduleWrongAnswerReset,
  punRevealHintWithModal,
} from '../../utils/punPlayShared'

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

const feedback = ref([])
const slotShake = ref(false)
const { showSuccess, runPassSuccess } = usePunPassSuccess()
const hintLoading = ref(false)
const hintAnswerQuota = ref(0)
const { markShareIntent, withShareReward } = usePunShareReward(hintAnswerQuota)
const answerInputValue = ref('')
const {
  inputRef: xhsInputRef,
  caretIndex,
  onInputFocus,
  onInputBlur,
  focusInput,
  onAnswerInput,
} = usePunHanAnswerInput({
  answerLen,
  answerChars,
  answerInputValue,
  onFilled: () => checkAnswer(),
})

function isSlotError(index) {
  return punIsSlotError(feedback.value, index)
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
      runPassSuccess({
        durationMs: 1200,
        afterPrepare: () => resolveNextXhsLevel(),
        onAfter: (nextLevel) => {
          if (nextLevel == null) {
            uni.showToast({ title: '专辑持续更新中，敬请期待~', icon: 'none' })
            setTimeout(() => {
              uni.reLaunch({ url: '/pages/index/index' })
            }, 1200)
            return
          }
          uni.redirectTo({ url: `/pages/playXhs/playXhs?level=${nextLevel}` })
        },
      })
      return
    }
    feedback.value = data.feedback || []
    punScheduleWrongAnswerReset(slotShake, () => {
      answerChars.value = []
      answerInputValue.value = ''
      feedback.value = []
    })
  } catch (e) {
    uni.showToast({ title: e.message || '提交失败', icon: 'none' })
  } finally {
    submitting.value = false
  }
}

async function resolveNextXhsLevel() {
  try {
    const prog = await api.getLevelProgress({ gameTier: 'xhs' })
    const candidate = prog && prog.currentLevel != null ? Number(prog.currentLevel) : NaN
    if (Number.isFinite(candidate) && candidate > 0 && candidate !== level.value) {
      return candidate
    }
  } catch (_) {}
  return getXhsNextLevel(level.value)
}

async function onRevealHint() {
  if (hintLoading.value || loading.value) return
  if (hintAnswerQuota.value <= 0) {
    uni.showToast({ title: '提示次数不足，请前往首页获取更多', icon: 'none' })
    return
  }
  hintLoading.value = true
  try {
    const res = await punRevealHintWithModal({ level: level.value, gameTier: 'xhs' })
    if (typeof res.hintAnswerQuota === 'number') {
      hintAnswerQuota.value = res.hintAnswerQuota
    }
  } catch (e) {
    uni.showToast({ title: e.message || '获取失败', icon: 'none' })
  } finally {
    hintLoading.value = false
  }
}

function help() {
  // #ifndef MP-WEIXIN
  uni.showToast({ title: '分享给好友一起猜～', icon: 'none' })
  // #endif
}

function back() {
  uni.reLaunch({ url: '/pages/index/index' })
}

function goFeedback() {
  const title = `小红书专辑 · 第${level.value}关`
  const query = `type=bug&passedLevels=${encodeURIComponent(String(level.value))}&content=${encodeURIComponent(title)}&contentFocus=1`
  uni.navigateTo({ url: `/pages/feedback/feedback?${query}` })
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
      if (typeof data.hintAnswerQuota === 'number') {
        hintAnswerQuota.value = data.hintAnswerQuota
      }
      await loadXhsLevelList()
      const resolved = pickXhsLevelFromProgress(data)
      lv = resolved != null ? resolved : 1
    } catch {
      lv = 1
    }
  } else {
    api.getLevelProgress({ gameTier: 'xhs' }).then((data) => {
      if (typeof data.hintAnswerQuota === 'number') {
        hintAnswerQuota.value = data.hintAnswerQuota
      }
    }).catch(() => {})
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
      prefetchNextXhsLevelImage(lv)
    })
    .catch(() => {
      loading.value = false
    })
})

onShareAppMessage(() => {
  return withShareReward({
    title: `小红书专辑·第${level.value}关，快来帮我猜！`,
    path: `/pages/playXhs/playXhs?level=${level.value}`,
  })
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
  position: relative;
  min-height: 420rpx;
  padding: 36rpx 28rpx 32rpx;
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

.main-img {
  width: 100%;
  height: 846rpx;
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

</style>
