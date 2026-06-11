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
        <text class="nav-title">{{ filterLabel }} · 第{{ displayIndex }}关</text>
      </template>
    </PunPageNavBar>

    <view class="card card--xhs">
      <view v-if="puzzle.keywordHint && !hideKeywordHint" class="keyword-tab">
        <text class="keyword-tab-text">提示：{{ puzzle.keywordHint }}</text>
      </view>
      <view class="author-tag">
        <text class="author-text">作者：绿泡泡【谐音梗猜一猜】</text>
      </view>
      <view class="card-inner">
        <PunGlobalWatermark />
        <PunWrongAnswerFloat :items="wrongFloatItems" />
        <image
          v-if="puzzle.imageUrlTop"
          class="main-img"
          :src="puzzle.imageUrlTop"
          mode="aspectFill"
        />
        <view v-else-if="loading" class="img-placeholder">加载中...</view>
        <view v-else class="img-placeholder">暂无配图</view>
        <view class="report-entry" @click="goFeedback">报错</view>
      </view>
    </view>

    <view class="answer-block">
      <view class="answer-row">
        <view class="answer-left">
          <input
            ref="inputRef"
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

    <PunPassSuccessOverlay
      :show="showSuccess"
      variant="plain"
      :sub-text="passSuccessExplain"
      :tap-anywhere="true"
      @action="confirmPassSuccess"
    />
    <PunHintOverlay
      :show="hintOverlayVisible"
      :hint-text="hintOverlayText"
      :step="hintOverlayStep"
      :max-steps="hintOverlayMaxSteps"
      :is-complete="hintOverlayComplete"
      @close="hintOverlayVisible = false"
    />
  </view>
</template>

<script setup>
import { ref, computed } from "vue"
import { onLoad, onShow, onHide, onShareAppMessage } from "@dcloudio/uni-app"
import {
  getFilteredXhsLevelPuzzle,
  loadFilteredXhsLevelList,
  getFilteredXhsLevelListForType,
  prefetchNextFilteredXhsLevelImage,
} from "../../data/albumLevels"
import { api } from "../../utils/api"
import { useNavBar } from "../../composables/useNavBar"
import PunPageNavBar from "../../components/PunPageNavBar.vue"
import PunPlayHintShareBar from "../../components/PunPlayHintShareBar.vue"
import PunPassSuccessOverlay from "../../components/PunPassSuccessOverlay.vue"
import PunHintOverlay from "../../components/PunHintOverlay.vue"
import PunWrongAnswerFloat from "../../components/PunWrongAnswerFloat.vue"
import PunGlobalWatermark from "../../components/PunGlobalWatermark.vue"
import { usePunPassSuccess } from "../composables/usePunPassSuccess"
import { usePunShareReward } from "../../composables/usePunShareReward"
import { usePunRewardedVideoHint } from "../composables/usePunRewardedVideoHint"
import { usePunHanAnswerInput } from "../composables/usePunHanAnswerInput"
import { useWrongAnswerFloat } from "../composables/useWrongAnswerFloat"
import { playBgmPlay, stopBgm } from "../../utils/gameAudio"
import { resolvePassExplain } from "../utils/punPassExplain"
import {
  punGetCachedHint,
  punIsSlotError,
  punScheduleWrongAnswerReset,
  punRevealHintWithModal,
  punToastRevealHintAfterError,
  SHARE_SUCCESS_THRESHOLD_MS,
} from "../utils/punPlayShared"

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()

const filterType = ref("")
const filterLabel = ref("")
const level = ref(1)
const answerLen = ref(3)
const answerChars = ref([])
const puzzle = ref({ imageUrlTop: "", keywordHint: "", author: "" })
const loading = ref(true)
const submitting = ref(false)
const hideKeywordHint = ref(false)
const filteredLevels = ref([])

const displayIndex = computed(() => {
  const idx = filteredLevels.value.indexOf(level.value)
  return idx >= 0 ? idx + 1 : 1
})

const feedback = ref([])
const slotShake = ref(false)
const { wrongFloatItems, pushWrongFloatText, clearWrongFloatText } = useWrongAnswerFloat()
const { showSuccess, runPassSuccess, confirmPassSuccess } = usePunPassSuccess()
const passSuccessExplain = ref("")
const hintLoading = ref(false)
const hintAnswerQuota = ref(0)
const hintOverlayVisible = ref(false)
const hintOverlayText = ref("")
const hintOverlayStep = ref(0)
const hintOverlayMaxSteps = ref(0)
const hintOverlayComplete = ref(false)
const { markShareIntent, withShareReward } = usePunShareReward(hintAnswerQuota, {
  mode: "heuristic",
  shareSuccessThresholdMs: SHARE_SUCCESS_THRESHOLD_MS,
  showCancelToast: true,
})
const { tryWatchAdForHintQuota } = usePunRewardedVideoHint(hintAnswerQuota)
const answerInputValue = ref("")
const { inputRef, caretIndex, onInputFocus, onInputBlur, focusInput, onAnswerInput } = usePunHanAnswerInput({
  answerLen, answerChars, answerInputValue,
  onFilled: () => checkAnswer(),
})

function safeDecode(str) {
  try { return decodeURIComponent(str) } catch { return str }
}

function isSlotError(index) { return punIsSlotError(feedback.value, index) }

async function checkAnswer() {
  if (submitting.value) return
  const userAnswer = answerChars.value.slice()
  if (userAnswer.length !== answerLen.value) return
  submitting.value = true
  feedback.value = []
  try {
    const data = await api.submitAnswer(level.value, userAnswer, { gameTier: "album" })
    if (data.isCorrect) {
      clearWrongFloatText()
      runPassSuccess({
        durationMs: 1200,
        manualClose: true,
        afterPrepare: async () => {
          applyPassExplain(data.passExplain)
          const idx = filteredLevels.value.indexOf(level.value)
          return idx >= 0 && idx + 1 < filteredLevels.value.length ? filteredLevels.value[idx + 1] : null
        },
        onAfter: (nextLevel) => {
          if (nextLevel == null) {
            uni.showToast({ title: filterLabel.value + "专辑已全部通关~", icon: "none" })
            setTimeout(() => uni.reLaunch({ url: "/pages/index/index" }), 1200)
            return
          }
          const ft = encodeURIComponent(filterType.value)
          const lb = encodeURIComponent(filterLabel.value)
          uni.redirectTo({ url: "/pages-sub/playAlbum/playAlbum?level=" + nextLevel + "&filterType=" + ft + "&label=" + lb })
        },
      })
      return
    }
    feedback.value = data.feedback || []
    pushWrongFloatText(userAnswer)
    punScheduleWrongAnswerReset(slotShake, () => { answerChars.value = []; answerInputValue.value = ""; feedback.value = [] })
  } catch (e) {
    uni.showToast({ title: e.message || "提交失败", icon: "none" })
  } finally {
    submitting.value = false
  }
}

function applyPassExplain(apiExplain) { passSuccessExplain.value = resolvePassExplain(apiExplain) }

async function onRevealHint() {
  if (hintLoading.value || loading.value) return
  const hintPayload = { level: level.value, gameTier: "album" }
  const cached = punGetCachedHint(hintPayload)
  if (cached && cached.isComplete) {
    hintOverlayText.value = String(cached.hintText || "")
    hintOverlayStep.value = Number(cached.step || 0)
    hintOverlayMaxSteps.value = Number(cached.maxSteps || 0)
    hintOverlayComplete.value = true
    hintOverlayVisible.value = true
    return
  }
  let afterVideo = false
  if (hintAnswerQuota.value <= 0) {
    afterVideo = true
    hintLoading.value = true
    try { if (!(await tryWatchAdForHintQuota())) { uni.showToast({ title: "次数不足", icon: "none" }); return } } finally { hintLoading.value = false }
    if (hintAnswerQuota.value <= 0) { uni.showToast({ title: "次数不足", icon: "none" }); return }
  }
  hintLoading.value = true
  try {
    const res = await punRevealHintWithModal(hintPayload)
    if (typeof res.hintAnswerQuota === "number") hintAnswerQuota.value = res.hintAnswerQuota
    hintOverlayText.value = String(res.hintText || "")
    hintOverlayStep.value = Number(res.step || 0)
    hintOverlayMaxSteps.value = Number(res.maxSteps || 0)
    hintOverlayComplete.value = !!res.isComplete
    hintOverlayVisible.value = true
  } catch (e) { punToastRevealHintAfterError(e, afterVideo) } finally { hintLoading.value = false }
}

function help() {}

function back() { uni.reLaunch({ url: "/pages/index/index" }) }

function goFeedback() {
  const title = filterLabel.value + " · 第" + displayIndex.value + "关"
  const query = "type=bug&passedLevels=" + encodeURIComponent(String(level.value)) + "&content=" + encodeURIComponent(title) + "&contentFocus=1"
  uni.navigateTo({ url: "/pages-sub/feedback/feedback?" + query })
}

onShow(() => playBgmPlay())
onHide(() => stopBgm())

onLoad(async (opts) => {
  const ft = safeDecode(opts && opts.filterType ? String(opts.filterType).trim() : "")
  const lb = safeDecode(opts && opts.label ? String(opts.label).trim() : "")
  filterType.value = ft
  filterLabel.value = lb || ft
  let lv = opts && opts.level != null && opts.level !== "" ? parseInt(opts.level, 10) : NaN
  if (!(Number.isFinite(lv) && lv > 0)) {
    loading.value = true
    try {
      await loadFilteredXhsLevelList(ft)
      const list = getFilteredXhsLevelListForType(ft)
      filteredLevels.value = list
      lv = list.length > 0 ? list[0] : 1
    } catch { lv = 1 }
  } else {
    loadFilteredXhsLevelList(ft).then(() => { filteredLevels.value = getFilteredXhsLevelListForType(ft) }).catch(() => {})
  }
  level.value = lv
  // 加载揭字次数（仅取 quota，不使用进度数据）
  api.getLevelProgress({ gameTier: "xhs" }).then((data) => {
    if (typeof data.hintAnswerQuota === "number") hintAnswerQuota.value = data.hintAnswerQuota
  }).catch(() => {})
  loading.value = true
  try { const raw = uni.getStorageSync("pun_hide_keyword_hint"); hideKeywordHint.value = raw === true || raw === "true" || raw === 1 || raw === "1" } catch {}
  getFilteredXhsLevelPuzzle(lv, ft).then((data) => {
    answerLen.value = data.answerLength || 3
    puzzle.value = { imageUrlTop: data.imageUrlTop || "", keywordHint: data.keywordHint || "", author: data.author || "" }
    answerChars.value = []; feedback.value = []; answerInputValue.value = ""; passSuccessExplain.value = ""
    loading.value = false
    prefetchNextFilteredXhsLevelImage(lv, ft)
  }).catch(() => { loading.value = false })
})

onShareAppMessage(() => {
  const ft = encodeURIComponent(filterType.value)
  const lb = encodeURIComponent(filterLabel.value)
  const lv = level.value
  return withShareReward({
    title: filterLabel.value + "·第" + displayIndex.value + "关，快来帮我猜！",
    path: "/pages-sub/playAlbum/playAlbum?level=" + lv + "&filterType=" + ft + "&label=" + lb,
  })
})
</script>

<style lang="scss" scoped>
@use "../../styles/page-theme.scss" as *;

.page {
  min-height: 100vh; position: relative; padding-bottom: 48rpx;
  padding-left: 40rpx; padding-right: 40rpx; box-sizing: border-box; overflow: hidden;
  @include pt-page-background;
}

.card {
  position: relative; z-index: 2; margin-bottom: 20rpx; border-radius: 24rpx; overflow: visible;
  box-shadow: 0 12rpx 36rpx rgba(169,201,238,.22), 0 2rpx 10rpx rgba(0,0,0,.05);
  background: rgba(255,255,255,.95); border: 2rpx solid rgba(169,201,238,.55);
}

.keyword-tab {
  position: absolute; top: 26rpx; right: 18rpx; z-index: 4; padding: 14rpx 20rpx;
  background: linear-gradient(145deg,#f6b0c3 0%,#ee8fad 100%); border-radius: 14rpx;
  box-shadow: 0 10rpx 24rpx rgba(238,143,173,.28);
}
.keyword-tab-text { font-size: 24rpx; font-weight: 700; color: #fff; }

.author-tag {
  position: absolute; top: 26rpx; left: 18rpx; z-index: 4; padding: 12rpx 16rpx;
  background: rgba(255,255,255,.88); border: 1rpx solid rgba(169,201,238,.45); border-radius: 12rpx;
}
.author-text { font-size: 22rpx; color: #7d8fa0; font-weight: 600; }

.card-inner { position: relative; min-height: 420rpx; padding: 20rpx; }
.report-entry { @include pt-report-entry; }

.main-img { width: 100%; height: 846rpx; border-radius: 20rpx; background: #f0f4f8; }
.img-placeholder {
  width: 100%; height: 520rpx; border-radius: 20rpx; background: rgba(240,244,248,.95);
  display: flex; align-items: center; justify-content: center; font-size: 28rpx; color: #8a9aaa;
}

.answer-block { @include pt-answer-block; }
.answer-row { @include pt-answer-row; border-bottom: 1rpx solid rgba(160,190,220,.35); }
.answer-left { @include pt-answer-left; }
.answer-input-cover { @include pt-answer-input-cover; }
.answer-slots { @include pt-answer-slots; }
.slot { @include pt-slot; }
.slot-error { @include pt-slot-error; }
.slot-shake { @include pt-slot-shake; }
.slot-caret { @include pt-slot-caret; }
@include pt-keyframes-caret-blink;
@include pt-keyframes-slot-shake;
</style>
