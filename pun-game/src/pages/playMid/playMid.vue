<template>
  <view class="page page--mid">
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
        <text class="nav-title" v-if="level !== 0">经典 · 第{{ level }}关</text>
        <text class="nav-title" v-else>经典 · 新手引导</text>
      </template>
    </PunPageNavBar>

    <view class="card card--mid">
      <view v-if="puzzle.keywordHint" class="keyword-tab">
        <text class="keyword-tab-text">{{ puzzle.keywordHint }}</text>
      </view>

      <view class="card-inner card-inner--stack">
        <view class="stack-block">
          <image v-if="puzzle.imageUrlTop" class="stack-img" :src="puzzle.imageUrlTop" mode="aspectFill" />
          <view v-else-if="loading" class="stack-placeholder">加载中...</view>
          <view v-else class="stack-placeholder">暂无配图</view>
          <text v-if="puzzle.topCaption" class="stack-caption">{{ puzzle.topCaption }}</text>
        </view>

        <view class="stack-block">
          <image v-if="puzzle.imageUrlBottom" class="stack-img" :src="puzzle.imageUrlBottom" mode="aspectFill" />
          <view v-else-if="loading" class="stack-placeholder">加载中...</view>
          <view v-else class="stack-placeholder">暂无下图</view>
          <!-- 你给的数据里 bottomcaption 没有单独字段，这里预留留白 -->
          <text v-if="puzzle.bottomCaption" class="stack-caption">{{ puzzle.bottomCaption }}</text>
        </view>
        <view class="report-entry" @click="goFeedback">报错</view>
      </view>
    </view>

    <view class="answer-block answer-block--mid">
      <view class="answer-row answer-row--mid">
        <view class="answer-left">
          <input
            ref="midInputRef"
            class="answer-mid-input-cover"
            type="text"
            :value="answerInputValue"
            @input="onMidAnswerInput"
            confirm-type="done"
            @focus="onMidInputFocus"
            @blur="onMidInputBlur"
            @click.stop="focusMidInput()"
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

    <!-- <view class="stuck-tip">
      <text>若通过后没进入下一关，请点击左上角重新游戏</text>
    </view> -->

    <PunPassSuccessOverlay :show="showSuccess" variant="rich" />
  </view>
</template>

<script setup>
import { ref } from 'vue'
import { onLoad, onShow, onHide, onShareAppMessage, onShareTimeline } from '@dcloudio/uni-app'
import {
  getMidLevelPuzzle,
  getMidNextLevel,
  loadMidLevelList,
  pickMidLevelFromProgress,
  prefetchNextMidLevelImages,
} from '../../data/levels'
import { api } from '../../utils/api'
import { useNavBar } from '../../composables/useNavBar'
import PunPageNavBar from '../../components/PunPageNavBar.vue'
import PunPlayHintShareBar from '../../components/PunPlayHintShareBar.vue'
import PunPassSuccessOverlay from '../../components/PunPassSuccessOverlay.vue'
import { usePunPassSuccess } from '../../composables/usePunPassSuccess'
import { usePunShareReward } from '../../composables/usePunShareReward'
import { usePunRewardedVideoHint } from '../../composables/usePunRewardedVideoHint'
import { usePunHanAnswerInput } from '../../composables/usePunHanAnswerInput'
import { playBgmPlay, stopBgm } from '../../utils/gameAudio'
import {
  punIsSlotError,
  punScheduleWrongAnswerReset,
  punRevealHintWithModal,
} from '../../utils/punPlayShared'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()

const level = ref(0)
const answerLen = ref(3)
const answerChars = ref([])
const puzzle = ref({
  imageUrlTop: '',
  imageUrlBottom: '',
  topCaption: '',
  bottomCaption: '',
  keywordHint: '',
})
const loading = ref(true)
const submitting = ref(false)

const feedback = ref([])
const slotShake = ref(false)
const { showSuccess, runPassSuccess } = usePunPassSuccess()
const hintLoading = ref(false)
const hintAnswerQuota = ref(0)
const { markShareIntent, withShareReward } = usePunShareReward(hintAnswerQuota)
const { tryWatchAdForHintQuota } = usePunRewardedVideoHint(hintAnswerQuota)

const answerInputValue = ref('')
const {
  inputRef: midInputRef,
  caretIndex,
  onInputFocus: onMidInputFocus,
  onInputBlur: onMidInputBlur,
  focusInput: focusMidInput,
  onAnswerInput: onMidAnswerInput,
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
  // answerChars 已按 answerLength 计算（仅在输入里出现汉字后更新）
  const userAnswer = answerChars.value.slice()
  if (userAnswer.length !== answerLen.value) return
  submitting.value = true
  feedback.value = []

  try {
    const data = await api.submitAnswer(level.value, userAnswer, { gameTier: 'mid' })
    if (data.isCorrect) {
      runPassSuccess({
        durationMs: 1500,
        afterPrepare: () => resolveNextMidLevel(),
        onAfter: (nextLevel) => {
          if (nextLevel == null) {
            uni.showToast({ title: '关卡持续更新中,敬请期待,您可以前往首页关卡继续游玩~', icon: 'none' })
            setTimeout(() => {
              uni.reLaunch({ url: '/pages/index/index' })
            }, 1500)
            return
          }
          // 使用 redirectTo 替换当前页，避免连续 navigateTo 堆满页面栈（微信约 10 层上限）
          uni.redirectTo({ url: `/pages/playMid/playMid?level=${nextLevel}` })
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

async function resolveNextMidLevel() {
  try {
    const prog = await api.getLevelProgress({ gameTier: 'mid' })
    const candidate = prog && prog.currentLevel != null ? Number(prog.currentLevel) : NaN
    if (Number.isFinite(candidate) && candidate >= 0 && candidate !== level.value) {
      return candidate
    }
  } catch (_) {}
  return getMidNextLevel(level.value)
}

function back() {
  uni.reLaunch({ url: '/pages/index/index' })
}

function goFeedback() {
  const title = level.value === 0 ? '经典 · 新手引导' : `经典 · 第${level.value}关`
  const query = `type=bug&passedLevels=${encodeURIComponent(String(level.value))}&content=${encodeURIComponent(title)}&contentFocus=1`
  uni.navigateTo({ url: `/pages/feedback/feedback?${query}` })
}

async function onRevealHint() {
  if (hintLoading.value || loading.value) return
  if (hintAnswerQuota.value <= 0) {
    // #ifdef MP-WEIXIN
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
    const res = await punRevealHintWithModal({ level: level.value, gameTier: 'mid' })
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

onShow(() => {
  playBgmPlay()
})

onHide(() => {
  stopBgm()
})

onLoad(async (opts) => {
  let lv = opts && opts.level != null && opts.level !== '' ? parseInt(opts.level, 10) : NaN
  const fromQuery = Number.isFinite(lv) && lv >= 0

  if (!fromQuery) {
    loading.value = true
    try {
      const data = await api.getLevelProgress({ gameTier: 'mid' })
      if (typeof data.hintAnswerQuota === 'number') {
        hintAnswerQuota.value = data.hintAnswerQuota
      }
      await loadMidLevelList()
      const resolved = pickMidLevelFromProgress(data)
      lv = resolved != null ? resolved : 0
    } catch {
      lv = 0
    }
  } else {
    api.getLevelProgress({ gameTier: 'mid' }).then((data) => {
      if (typeof data.hintAnswerQuota === 'number') {
        hintAnswerQuota.value = data.hintAnswerQuota
      }
    }).catch(() => {})
  }

  level.value = lv
  loading.value = true

  getMidLevelPuzzle(lv).then((data) => {
    answerLen.value = data.answerLength || 3
    puzzle.value = {
      imageUrlTop: data.imageUrlTop || '',
      imageUrlBottom: data.imageUrlBottom || '',
      topCaption: data.topCaption || '',
      bottomCaption: data.bottomCaption || '',
      keywordHint: data.keywordHint || '',
    }
    answerChars.value = []
    feedback.value = []
    answerInputValue.value = ''
    loading.value = false
    prefetchNextMidLevelImages(lv)
  }).catch(() => {
    loading.value = false
  })
})

onShareAppMessage(() => {
  return withShareReward({
    title: `中级第${level.value}关，快来帮我猜谐音梗！`,
    path: `/pages/playMid/playMid?level=${level.value}`,
  })
})

onShareTimeline(() => {
  const img =
    (puzzle.value.imageUrlTop && String(puzzle.value.imageUrlTop).startsWith('http') && puzzle.value.imageUrlTop) ||
    (puzzle.value.imageUrlBottom && String(puzzle.value.imageUrlBottom).startsWith('http') && puzzle.value.imageUrlBottom) ||
    ''
  return {
    title: `中级第${level.value}关，快来帮我猜谐音梗！`,
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
  max-width: 100vh;
  overflow: hidden;
  @include pt-page-background;
}

.nav-star { font-size: 30rpx; }
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
.help-icon { font-size: 30rpx; }
.help-text { font-size: 26rpx; font-weight: 500; }

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
.card--mid {
  overflow: visible;
  border-radius: 28rpx;
  box-shadow: 0 12rpx 36rpx rgba(169, 201, 238, 0.22), 0 2rpx 10rpx rgba(0,0,0,0.05);
  border-color: rgba(169, 201, 238, 0.55);
}

.keyword-tab {
  position: absolute;
  top: 26rpx;
  right: 18rpx;
  z-index: 4;
  min-height: auto;
  padding: 14rpx 20rpx;
  background: linear-gradient(145deg, #a8e6a2 0%, #91d58b 100%);
  border-radius: 14rpx;
  box-shadow: 0 10rpx 24rpx rgba(111, 184, 104, 0.28);
  display: flex;
  align-items: center;
  justify-content: center;
}
.keyword-tab-text {
  font-size: 26rpx;
  font-weight: 700;
  color: #fff;
  writing-mode: horizontal-tb;
  letter-spacing: 0.02em;
  line-height: 1;
}

.card-inner {
  position: relative;
  min-height: 400rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  border-radius: 24rpx;
}
.card-inner--stack {
  min-height: auto;
  padding: 36rpx 28rpx 72rpx;
  gap: 28rpx;
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
.stack-block {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16rpx;
}
.stack-img {
  width: 100%;
  border-radius: 20rpx;
  background: #f0f4f8;
}
.stack-placeholder {
  width: 100%;
  height: 220rpx;
  border-radius: 20rpx;
  background: rgba(240, 244, 248, 0.95);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28rpx;
  color: #8a9aaa;
}
.stack-caption {
  font-size: 30rpx;
  font-weight: 700;
  color: #2c3a4a;
  text-align: center;
  line-height: 1.5;
  padding: 0 8rpx;
  margin-top: -90rpx;
}

/* 答题区 + 底部操作条：同一卡片，操作条单独一行 */
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

.answer-left {
  position: relative; /* 让输入透明层只覆盖左侧区域 */
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.answer-row--mid {
  border-bottom: 1rpx solid rgba(160, 190, 220, 0.35);
}
.answer-mid-input-cover {
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

.answer-btn-placeholder {
  width: 100%;
  min-height: 86rpx;
  border-radius: 20rpx;
  background: rgba(255, 255, 255, 0.15);
  border: 2rpx dashed rgba(169, 201, 238, 0.4);
}

.slot-char {
  // 保证字符与原槽位视觉居中一致
  line-height: 1;
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
.stuck-tip {
  position: relative;
  z-index: 2;
  text-align: center;
  font-size: 24rpx;
  color: #8a9aaa;
  margin-top: -16rpx;
  margin-bottom: 32rpx;
}
.answer-row--mid .slot {
  border-color: rgba(140, 170, 200, 0.45);
  background: rgba(248, 252, 255, 0.95);
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

</style>

