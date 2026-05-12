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
      title="任务中心"
      @left-click="back"
    />

    <view class="section">
      <!-- 答案次数概览 -->
      <view class="quota-banner">
        <text class="quota-banner-label">当前答案次数</text>
        <view class="quota-banner-value-row">
          <text class="quota-banner-num">{{ hintAnswerQuota }}</text>
          <text class="quota-banner-unit">次</text>
        </view>
      </view>

      <!-- 永久任务 -->
      <view class="task-section">
        <view class="task-section-header task-section-header--permanent">
          <view class="task-section-header-left">
            <text class="task-section-icon">🏆</text>
            <text class="task-section-title">永久任务</text>
          </view>
          <text class="task-section-badge">限领一次</text>
        </view>
        <view class="task-row">
          <view class="task-row-info">
            <text class="task-row-icon">🖼️</text>
            <view class="task-row-main">
              <text class="task-row-name">首次设置头像</text>
              <text class="task-row-sub">设置头像后可领 <text class="task-row-reward">+3次</text> 查看答案</text>
            </view>
          </view>
          <button
            class="task-btn"
            :class="avatarTaskClaimed ? 'task-btn--done' : 'task-btn--permanent'"
            :disabled="avatarTaskClaimed || avatarTaskClaimLoading"
            @click.stop="claimAvatarTask"
          >
            {{ avatarTaskClaimed ? '✓ 已领取' : (avatarTaskClaimLoading ? '领取中…' : '领取') }}
          </button>
        </view>
        <view class="task-divider" />
        <view class="task-row">
          <view class="task-row-info">
            <text class="task-row-icon">✏️</text>
            <view class="task-row-main">
              <text class="task-row-name">首次设置昵称</text>
              <text class="task-row-sub">设置昵称后可领 <text class="task-row-reward">+3次</text> 查看答案</text>
            </view>
          </view>
          <button
            class="task-btn"
            :class="nicknameTaskClaimed ? 'task-btn--done' : 'task-btn--permanent'"
            :disabled="nicknameTaskClaimed || nicknameTaskClaimLoading"
            @click.stop="claimNicknameTask"
          >
            {{ nicknameTaskClaimed ? '✓ 已领取' : (nicknameTaskClaimLoading ? '领取中…' : '领取') }}
          </button>
        </view>
        <view class="task-divider" />
        <view class="task-row">
          <view class="task-row-info">
            <text class="task-row-icon">⭐</text>
            <view class="task-row-main">
              <text class="task-row-name">收藏到我的小程序</text>
              <text class="task-row-sub">
                添加到微信「我的小程序」或抖音「我的收藏」后，<text class="task-row-reward">从该入口进入</text> 可领 <text class="task-row-reward">+3次</text>
                <template v-if="!myMiniProgramTaskClaimed && !launchFromMyMiniOrCollect">
                  · 请关闭小程序后从上述入口重新打开
                </template>
              </text>
            </view>
          </view>
          <button
            class="task-btn"
            :class="myMiniProgramTaskClaimed ? 'task-btn--done' : (launchFromMyMiniOrCollect ? 'task-btn--permanent' : 'task-btn--goto')"
            :disabled="myMiniProgramTaskClaimed || myMiniProgramTaskLoading"
            @click.stop="claimMyMiniProgramTask"
          >
            {{ myMiniProgramTaskClaimed ? '✓ 已领取' : (myMiniProgramTaskLoading ? '领取中…' : '领取') }}
          </button>
        </view>
      </view>

      <!-- 每日任务 -->
      <view class="task-section">
        <view class="task-section-header task-section-header--daily">
          <view class="task-section-header-left">
            <text class="task-section-icon">📅</text>
            <text class="task-section-title">每日任务</text>
          </view>
          <text class="task-section-badge">每天零点重置</text>
        </view>
        <view class="task-row">
          <view class="task-row-info">
            <text class="task-row-icon">🎁</text>
            <view class="task-row-main">
              <text class="task-row-name">答题奖励</text>
              <view class="task-row-progress-wrap">
                <view class="task-row-progress-bar">
                  <view
                    class="task-row-progress-fill"
                    :style="{ width: Math.min(100, Math.round(dailyAnswerCount / dailyAnswerRequired * 100)) + '%' }"
                  />
                </view>
                <text class="task-row-sub">{{ dailyAnswerCount }}/{{ dailyAnswerRequired }} 题 · 完成后领 <text class="task-row-reward">+5次</text></text>
              </view>
            </view>
          </view>
          <button
            class="task-btn"
            :class="dailyNoonTaskClaimed ? 'task-btn--done' : (canClaimDailyReward ? 'task-btn--daily' : 'task-btn--goto')"
            :disabled="dailyNoonTaskClaimed || (canClaimDailyReward ? dailyClaimLoading : false)"
            @click.stop="onDailyRewardAction"
          >
            {{ dailyNoonTaskClaimed ? '✓ 已领取' : (canClaimDailyReward ? (dailyClaimLoading ? '领取中…' : '领取') : '去答题') }}
          </button>
        </view>
        <view class="task-divider" />
        <view class="task-row">
          <view class="task-row-info">
            <text class="task-row-icon">📣</text>
            <view class="task-row-main">
              <text class="task-row-name">分享领奖励</text>
              <text class="task-row-sub">今日 {{ shareDailyClaimed }}/{{ hintAnswerShareDailyMax }} 次 · 每次 <text class="task-row-reward">+1次</text> 查看答案</text>
            </view>
          </view>
          <!-- #ifdef MP-WEIXIN -->
          <button
            class="task-btn"
            :class="shareTaskClaimedAll ? 'task-btn--done' : 'task-btn--share'"
            :disabled="shareTaskClaimedAll || shareClaimLoading"
            open-type="share"
            @click="onShareTaskIntent"
          >
            {{ shareTaskClaimedAll ? '✓ 已完成' : (shareClaimLoading ? '领取中…' : (shareDailyClaimed > 0 ? '再次分享' : '去分享')) }}
          </button>
          <!-- #endif -->
          <!-- #ifdef MP-TOUTIAO -->
          <button
            class="task-btn"
            :class="shareTaskClaimedAll ? 'task-btn--done' : 'task-btn--share'"
            :disabled="shareTaskClaimedAll || shareClaimLoading"
            open-type="share"
            @click="onShareTaskIntent"
          >
            {{ shareTaskClaimedAll ? '✓ 已完成' : (shareClaimLoading ? '领取中…' : (shareDailyClaimed > 0 ? '再次分享' : '去分享')) }}
          </button>
          <!-- #endif -->
          <!-- #ifndef MP-WEIXIN -->
          <!-- #ifndef MP-TOUTIAO -->
          <button
            class="task-btn"
            :class="shareTaskClaimedAll ? 'task-btn--done' : 'task-btn--share'"
            :disabled="shareTaskClaimedAll || shareClaimLoading"
            @click="onShareTaskIntent"
          >
            {{ shareTaskClaimedAll ? '✓ 已完成' : (shareClaimLoading ? '领取中…' : (shareDailyClaimed > 0 ? '再次分享' : '去分享')) }}
          </button>
          <!-- #endif -->
          <!-- #endif -->
        </view>
        <view class="task-divider" />
        <view class="task-row">
          <view class="task-row-info">
            <text class="task-row-icon">🎬</text>
            <view class="task-row-main">
              <text class="task-row-name">看广告领答案</text>
              <text class="task-row-sub">今日已完成 {{ dailyAdTaskCount }} 次 · 不限次，每次 <text class="task-row-reward">+1次</text></text>
            </view>
          </view>
          <button
            class="task-btn task-btn--daily"
            :disabled="dailyAdTaskLoading"
            @click.stop="claimDailyAdTask"
          >
            {{ dailyAdTaskLoading ? '处理中…' : '去领取' }}
          </button>
        </view>
        <view class="task-divider" />
        <view class="task-row">
          <view class="task-row-info">
            <text class="task-row-icon">⚔️</text>
            <view class="task-row-main">
              <text class="task-row-name">1V1 对局任务</text>
              <view class="task-row-progress-wrap">
                <view class="task-row-progress-bar">
                  <view
                    class="task-row-progress-fill"
                    :style="{ width: Math.min(100, Math.round(dailyBattleCount / dailyBattleRequired * 100)) + '%' }"
                  />
                </view>
                <text class="task-row-sub">{{ dailyBattleCount }}/{{ dailyBattleRequired }} 局 · 完成后领 <text class="task-row-reward">+3次</text></text>
              </view>
            </view>
          </view>
          <button
            class="task-btn"
            :class="dailyBattleTaskClaimed ? 'task-btn--done' : (canClaimDailyBattleTask ? 'task-btn--daily' : 'task-btn--goto')"
            :disabled="dailyBattleTaskClaimed || (canClaimDailyBattleTask ? dailyBattleTaskLoading : false)"
            @click.stop="onDailyBattleTaskAction"
          >
            {{ dailyBattleTaskClaimed ? '✓ 已领取' : (canClaimDailyBattleTask ? (dailyBattleTaskLoading ? '领取中…' : '领取') : '去对局') }}
          </button>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import { onShow, onShareAppMessage, onShareTimeline } from '@dcloudio/uni-app'
import { useNavBar } from '../../composables/useNavBar'
import { usePunShareReward } from '../../composables/usePunShareReward'
import { SHARE_SUCCESS_THRESHOLD_MS } from '../../utils/punPlayShared'
import { api } from '../../utils/api'
import { REWARDED_VIDEO_AD_UNIT_ID } from '../../constants/rewardedVideoAd'
import PunPageNavBar from '../../components/PunPageNavBar.vue'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()

const hintAnswerQuota = ref(0)
const hintAnswerShareDailyMax = ref(5)
const shareDailyClaimed = ref(0)
const shareClaimLoading = ref(false)
const dailyAnswerCount = ref(0)
const dailyAnswerRequired = ref(20)
const dailyNoonTaskClaimed = ref(false)
const dailyAdTaskCount = ref(0)
const dailyBattleCount = ref(0)
const dailyBattleRequired = ref(3)
const dailyBattleTaskClaimed = ref(false)
const avatarTaskClaimed = ref(false)
const nicknameTaskClaimed = ref(false)
const myMiniProgramTaskClaimed = ref(false)
const avatarTaskClaimLoading = ref(false)
const nicknameTaskClaimLoading = ref(false)
const myMiniProgramTaskLoading = ref(false)
const launchFromMyMiniOrCollect = ref(false)
const dailyClaimLoading = ref(false)
const dailyAdTaskLoading = ref(false)
const dailyBattleTaskLoading = ref(false)

const canClaimDailyReward = computed(() => dailyAnswerCount.value >= dailyAnswerRequired.value)
const shareTaskClaimedAll = computed(
  () => hintAnswerShareDailyMax.value > 0 && shareDailyClaimed.value >= hintAnswerShareDailyMax.value
)
const canClaimDailyBattleTask = computed(
  () => !dailyBattleTaskClaimed.value && dailyBattleCount.value >= dailyBattleRequired.value
)

let shareLoadingTimer = null
let mineDailyVideoAd = null
let mineDailyVideoBusy = false

const platform = (() => {
  try {
    return (uni.getAppBaseInfo?.() ?? uni.getSystemInfoSync()).uniPlatform || ''
  } catch { return '' }
})()
const isMiniProgram = platform.startsWith('mp-')

const { markShareIntent: markShareIntentTask, withShareReward } = usePunShareReward(hintAnswerQuota, {
  mode: 'heuristic',
  shareSuccessThresholdMs: SHARE_SUCCESS_THRESHOLD_MS,
  showCancelToast: true,
  onClaimSuccess(payload) {
    if (shareLoadingTimer) { clearTimeout(shareLoadingTimer); shareLoadingTimer = null }
    shareClaimLoading.value = false
    const add = Number(payload && payload.added) > 0 ? Number(payload.added) : 1
    const max = Math.max(0, Math.floor(hintAnswerShareDailyMax.value || 0))
    shareDailyClaimed.value = Math.max(0, Math.min(max, Math.floor(shareDailyClaimed.value + add)))
    refreshTaskData()
  },
})

onShareAppMessage(() => withShareReward({ title: '任务中心 · 谐音梗猜一猜', path: '/pages/index/index' }))
onShareTimeline(() => withShareReward({ title: '任务中心 · 谐音梗猜一猜' }))

function isLaunchFromMyMiniOrCollectScene() {
  try {
    const enterOpts = typeof uni.getEnterOptionsSync === 'function' ? uni.getEnterOptionsSync() : null
    const launchOpts = typeof uni.getLaunchOptionsSync === 'function' ? uni.getLaunchOptionsSync() : null
    const scenes = [enterOpts?.scene, launchOpts?.scene].filter(Boolean)
    if (scenes.length === 0) return false
    const validScenes = [1103, 1104, 21003]
    for (const scene of scenes) {
      const n = Number(scene)
      if (!Number.isNaN(n) && validScenes.includes(n)) return true
      if (String(scene) === '021003') return true
    }
    return false
  } catch { return false }
}

async function refreshTaskData() {
  try {
    const data = await api.getLevelProgress({ gameTier: 'mid' })
    if (!data) return
    if (typeof data.hintAnswerQuota === 'number') hintAnswerQuota.value = data.hintAnswerQuota
    if (typeof data.hintAnswerShareDailyMax === 'number' && data.hintAnswerShareDailyMax > 0)
      hintAnswerShareDailyMax.value = Math.floor(data.hintAnswerShareDailyMax)
    if (typeof data.hintAnswerShareDailyClaimed === 'number' && data.hintAnswerShareDailyClaimed >= 0) {
      const max = Math.max(0, Math.floor(data.hintAnswerShareDailyMax ?? hintAnswerShareDailyMax.value))
      shareDailyClaimed.value = Math.max(0, Math.min(max, Math.floor(data.hintAnswerShareDailyClaimed)))
    }
    if (typeof data.dailyAnswerRequired === 'number' && data.dailyAnswerRequired > 0)
      dailyAnswerRequired.value = Math.floor(data.dailyAnswerRequired)
    if (typeof data.dailyAnswerCount === 'number' && data.dailyAnswerCount >= 0)
      dailyAnswerCount.value = Math.max(0, Math.floor(data.dailyAnswerCount))
    if (typeof data.dailyNoonTaskClaimed !== 'undefined')
      dailyNoonTaskClaimed.value = Number(data.dailyNoonTaskClaimed) > 0
    if (typeof data.dailyAdTaskCount === 'number' && data.dailyAdTaskCount >= 0)
      dailyAdTaskCount.value = Math.max(0, Math.floor(data.dailyAdTaskCount))
    if (typeof data.dailyBattleRequired === 'number' && data.dailyBattleRequired > 0)
      dailyBattleRequired.value = Math.max(1, Math.floor(data.dailyBattleRequired))
    if (typeof data.dailyBattleCount === 'number' && data.dailyBattleCount >= 0)
      dailyBattleCount.value = Math.max(0, Math.floor(data.dailyBattleCount))
    if (typeof data.dailyBattleTaskClaimed !== 'undefined')
      dailyBattleTaskClaimed.value = Number(data.dailyBattleTaskClaimed) > 0
    if (typeof data.avatarTaskClaimed !== 'undefined')
      avatarTaskClaimed.value = Number(data.avatarTaskClaimed) > 0
    if (typeof data.nicknameTaskClaimed !== 'undefined')
      nicknameTaskClaimed.value = Number(data.nicknameTaskClaimed) > 0
    if (typeof data.myMiniProgramTaskClaimed !== 'undefined')
      myMiniProgramTaskClaimed.value = Number(data.myMiniProgramTaskClaimed) > 0
  } catch {}
}

onShow(() => {
  launchFromMyMiniOrCollect.value = isLaunchFromMyMiniOrCollectScene()
  refreshTaskData()
})

function back() {
  uni.navigateBack({ delta: 1, fail: () => uni.reLaunch({ url: '/pages/index/index' }) })
}

function goHome() {
  uni.reLaunch({ url: '/pages/index/index' })
}

// ── 广告 ────────────────────────────────────────────────
function ensureMineDailyVideoAd() {
  if (mineDailyVideoAd) return mineDailyVideoAd
  const creator = typeof wx !== 'undefined' && typeof wx.createRewardedVideoAd === 'function'
    ? wx.createRewardedVideoAd
    : (typeof tt !== 'undefined' && typeof tt.createRewardedVideoAd === 'function' ? tt.createRewardedVideoAd : null)
  if (!creator) return null
  try {
    mineDailyVideoAd = creator({ adUnitId: REWARDED_VIDEO_AD_UNIT_ID })
    mineDailyVideoAd.onError(() => {})
  } catch { mineDailyVideoAd = null }
  return mineDailyVideoAd
}

function watchDailyTaskAd() {
  if (mineDailyVideoBusy) return Promise.resolve(false)
  const ad = ensureMineDailyVideoAd()
  if (!ad) {
    uni.showToast({ title: '当前环境不支持激励视频', icon: 'none' })
    return Promise.resolve(false)
  }
  mineDailyVideoBusy = true
  return new Promise((resolve) => {
    let settled = false
    const finish = (ok) => { if (settled) return; settled = true; mineDailyVideoBusy = false; resolve(ok) }
    const onClose = (res) => { try { ad.offClose(onClose) } catch (_) {}; finish(!!(res && res.isEnded)) }
    function loadAdWithTimeout(adInstance, ms = 10000) {
      return new Promise((res, rej) => {
        const t = setTimeout(() => rej(new Error('广告加载超时')), ms)
        adInstance.load().then(() => { clearTimeout(t); res() }).catch((e) => { clearTimeout(t); rej(e) })
      })
    }
    ad.onClose(onClose)
    ad.show()
      .catch(() => loadAdWithTimeout(ad, 10000).then(() => ad.show()))
      .catch((err) => {
        try { ad.offClose(onClose) } catch (_) {}
        uni.showToast({ title: err.message === '广告加载超时' ? '广告加载超时，请稍后重试' : '广告加载失败，请稍后重试', icon: 'none' })
        finish(false)
      })
  })
}

// ── 领取函数 ─────────────────────────────────────────────
async function claimDailyNoonReward() {
  if (dailyClaimLoading.value || dailyNoonTaskClaimed.value) return
  dailyClaimLoading.value = true
  try {
    const data = await api.claimReward({ type: 'daily_noon_hint_5', add: 5 })
    if (typeof data.hintAnswerQuota === 'number') hintAnswerQuota.value = data.hintAnswerQuota
    dailyNoonTaskClaimed.value = true
    uni.showToast({ title: `领取成功 +${data.added || 5}`, icon: 'none' })
    refreshTaskData()
  } catch (e) {
    uni.showToast({ title: e.message || '领取失败', icon: 'none' })
  } finally {
    dailyClaimLoading.value = false
  }
}

function onDailyRewardAction() {
  if (dailyNoonTaskClaimed.value) return
  if (!canClaimDailyReward.value) { uni.reLaunch({ url: '/pages/index/index' }); return }
  claimDailyNoonReward()
}

async function claimDailyAdTask() {
  if (dailyAdTaskLoading.value) return
  if (!isMiniProgram) { uni.showToast({ title: '仅小程序支持看广告领取', icon: 'none' }); return }
  if (!ensureMineDailyVideoAd()) { uni.showToast({ title: '当前平台暂不支持激励视频', icon: 'none' }); return }
  dailyAdTaskLoading.value = true
  try {
    const watched = await watchDailyTaskAd()
    if (!watched) { uni.showToast({ title: '请完整观看广告后领取', icon: 'none' }); return }
    const data = await api.claimReward({ type: 'daily_watch_ad_hint_1', add: 1 })
    if (typeof data.hintAnswerQuota === 'number') hintAnswerQuota.value = data.hintAnswerQuota
    dailyAdTaskCount.value = Math.max(0, dailyAdTaskCount.value + (Number(data.added) > 0 ? Number(data.added) : 1))
    uni.showToast({ title: `领取成功 +${data.added || 1}`, icon: 'none' })
    refreshTaskData()
  } catch (e) {
    uni.showToast({ title: e.message || '领取失败', icon: 'none' })
  } finally {
    dailyAdTaskLoading.value = false
  }
}

async function claimDailyBattleTask() {
  if (dailyBattleTaskLoading.value || dailyBattleTaskClaimed.value) return
  dailyBattleTaskLoading.value = true
  try {
    const data = await api.claimReward({ type: 'daily_battle_3_hint_3', add: 3 })
    if (typeof data.hintAnswerQuota === 'number') hintAnswerQuota.value = data.hintAnswerQuota
    dailyBattleTaskClaimed.value = true
    uni.showToast({ title: `领取成功 +${data.added || 3}`, icon: 'none' })
    refreshTaskData()
  } catch (e) {
    uni.showToast({ title: e.message || '领取失败', icon: 'none' })
  } finally {
    dailyBattleTaskLoading.value = false
  }
}

function onDailyBattleTaskAction() {
  if (dailyBattleTaskClaimed.value) return
  if (!canClaimDailyBattleTask.value) { uni.navigateTo({ url: '/pages/battleRoom/battleRoom' }); return }
  claimDailyBattleTask()
}

async function claimAvatarTask() {
  if (avatarTaskClaimLoading.value || avatarTaskClaimed.value) return
  avatarTaskClaimLoading.value = true
  try {
    const data = await api.claimReward({ type: 'permanent_set_avatar' })
    if (typeof data.hintAnswerQuota === 'number') hintAnswerQuota.value = data.hintAnswerQuota
    avatarTaskClaimed.value = true
    uni.showToast({ title: `领取成功 +${data.added || 3}`, icon: 'none' })
  } catch (e) {
    uni.showToast({ title: e.message || '领取失败', icon: 'none' })
  } finally {
    avatarTaskClaimLoading.value = false
  }
}

async function claimNicknameTask() {
  if (nicknameTaskClaimLoading.value || nicknameTaskClaimed.value) return
  nicknameTaskClaimLoading.value = true
  try {
    const data = await api.claimReward({ type: 'permanent_set_nickname' })
    if (typeof data.hintAnswerQuota === 'number') hintAnswerQuota.value = data.hintAnswerQuota
    nicknameTaskClaimed.value = true
    uni.showToast({ title: `领取成功 +${data.added || 3}`, icon: 'none' })
  } catch (e) {
    uni.showToast({ title: e.message || '领取失败', icon: 'none' })
  } finally {
    nicknameTaskClaimLoading.value = false
  }
}

async function claimMyMiniProgramTask() {
  if (myMiniProgramTaskLoading.value || myMiniProgramTaskClaimed.value) return
  launchFromMyMiniOrCollect.value = isLaunchFromMyMiniOrCollectScene()
  if (!launchFromMyMiniOrCollect.value) {
    uni.showToast({ title: '请先添加到「我的小程序/收藏」，关闭后从该入口重新进入', icon: 'none' })
    return
  }
  myMiniProgramTaskLoading.value = true
  try {
    let launchScene
    try {
      const enter = typeof uni.getEnterOptionsSync === 'function' ? uni.getEnterOptionsSync() : null
      launchScene = enter?.scene ?? uni.getLaunchOptionsSync()?.scene
    } catch { launchScene = undefined }
    const data = await api.claimReward({ type: 'permanent_my_mini_program_hint_3', launchScene })
    if (typeof data.hintAnswerQuota === 'number') hintAnswerQuota.value = data.hintAnswerQuota
    myMiniProgramTaskClaimed.value = true
    uni.showToast({ title: `领取成功 +${data.added || 3}`, icon: 'none' })
    refreshTaskData()
  } catch (e) {
    uni.showToast({ title: e.message || '领取失败', icon: 'none' })
  } finally {
    myMiniProgramTaskLoading.value = false
  }
}

function onShareTaskIntent() {
  if (!isMiniProgram) { uni.showToast({ title: '仅小程序支持分享领奖', icon: 'none' }); return }
  if (shareTaskClaimedAll.value) return
  shareClaimLoading.value = true
  if (shareLoadingTimer) { clearTimeout(shareLoadingTimer); shareLoadingTimer = null }
  shareLoadingTimer = setTimeout(() => { shareLoadingTimer = null; shareClaimLoading.value = false }, 6000)
  markShareIntentTask()
}

onBeforeUnmount(() => {
  if (shareLoadingTimer) { clearTimeout(shareLoadingTimer); shareLoadingTimer = null }
  shareClaimLoading.value = false
  if (mineDailyVideoAd && typeof mineDailyVideoAd.destroy === 'function') {
    try { mineDailyVideoAd.destroy() } catch (_) {}
  }
  mineDailyVideoAd = null
  mineDailyVideoBusy = false
})
</script>

<style lang="scss" scoped>
@use '../../styles/page-theme.scss' as *;

.page {
  min-height: 100vh;
  position: relative;
  padding: 0 40rpx 48rpx;
  padding-bottom: calc(48rpx + env(safe-area-inset-bottom));
  box-sizing: border-box;
  @include pt-page-background;
}

.section {
  position: relative;
  z-index: 2;
  width: 100%;
  margin: 20rpx auto 0;
}

.nav-home-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 60rpx;
  height: 60rpx;
  margin-right: 16rpx;
}

.nav-home-img {
  width: 40rpx;
  height: 40rpx;
}

.quota-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx 28rpx;
  margin-bottom: 20rpx;
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  border-radius: 24rpx;
  border: 2rpx solid rgba(52, 211, 153, 0.3);
  box-shadow: 0 4rpx 14rpx rgba(52, 211, 153, 0.18);
}

.quota-banner-label {
  font-size: 26rpx;
  font-weight: 700;
  color: #065f46;
}

.quota-banner-value-row {
  display: flex;
  align-items: baseline;
  gap: 4rpx;
}

.quota-banner-num {
  font-size: 52rpx;
  font-weight: 900;
  color: #059669;
  line-height: 1;
}

.quota-banner-unit {
  font-size: 24rpx;
  font-weight: 700;
  color: #059669;
}

.task-section {
  margin-bottom: 20rpx;
  background: rgba(255, 255, 255, 0.82);
  border-radius: 24rpx;
  border: 2rpx solid $pt-border;
  box-shadow: 0 6rpx 18rpx $pt-shadow-soft;
  overflow: hidden;
}

.task-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18rpx 24rpx 16rpx;
}

.task-section-header--permanent {
  background: linear-gradient(135deg, #f5f0ff 0%, #ede8ff 100%);
  border-bottom: 1rpx solid rgba(167, 139, 250, 0.25);
}

.task-section-header--daily {
  background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
  border-bottom: 1rpx solid rgba(251, 191, 36, 0.25);
}

.task-section-header-left {
  display: flex;
  align-items: center;
  gap: 10rpx;
}

.task-section-icon { font-size: 30rpx; line-height: 1; }

.task-section-title {
  font-size: 26rpx;
  font-weight: 800;
  letter-spacing: 0.04em;
  color: $pt-text;
}

.task-section-badge {
  font-size: 20rpx;
  font-weight: 600;
  color: $pt-muted;
  background: rgba(255, 255, 255, 0.7);
  padding: 4rpx 14rpx;
  border-radius: 100rpx;
}

.task-row {
  display: flex;
  align-items: center;
  padding: 22rpx 24rpx;
  gap: 16rpx;
}

.task-row-info {
  display: flex;
  align-items: flex-start;
  gap: 16rpx;
  flex: 1;
  min-width: 0;
}

.task-row-icon { font-size: 36rpx; line-height: 1; margin-top: 2rpx; flex-shrink: 0; }

.task-row-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6rpx;
}

.task-row-name { font-size: 27rpx; font-weight: 700; color: $pt-text; line-height: 1.3; }
.task-row-sub { font-size: 21rpx; color: $pt-muted; line-height: 1.45; }
.task-row-reward { font-size: 21rpx; font-weight: 700; color: #d97706; }

.task-row-progress-wrap { display: flex; flex-direction: column; gap: 6rpx; }

.task-row-progress-bar {
  width: 100%; height: 6rpx; border-radius: 6rpx;
  background: rgba(0, 0, 0, 0.08); overflow: hidden;
}

.task-row-progress-fill {
  height: 100%; border-radius: 6rpx;
  background: linear-gradient(90deg, #86efac 0%, #22c55e 100%);
  transition: width 0.3s ease;
}

.task-divider { height: 1rpx; margin: 0 24rpx; background: rgba(0, 0, 0, 0.05); }

.task-btn {
  flex-shrink: 0;
  min-width: 120rpx;
  height: 58rpx;
  line-height: 58rpx;
  border-radius: 100rpx;
  border: none;
  font-size: 23rpx;
  font-weight: 700;
  padding: 0 20rpx;
  text-align: center;
}

.task-btn::after { border: none; }
.task-btn--permanent { background: linear-gradient(135deg, #c4b5fd 0%, #a78bfa 100%); color: #4c1d95; }
.task-btn--daily { background: linear-gradient(135deg, #fde68a 0%, #f59e0b 100%); color: #78350f; }
.task-btn--share { background: linear-gradient(135deg, #bae6fd 0%, #38bdf8 100%); color: #0c4a6e; }
.task-btn--goto { background: rgba(0, 0, 0, 0.06); color: $pt-muted; }
.task-btn--done { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46; }

.task-btn[disabled].task-btn--done {
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%) !important;
  color: #065f46 !important;
}

.task-btn[disabled]:not(.task-btn--done) {
  background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%) !important;
  color: #9ca3af !important;
}
</style>
