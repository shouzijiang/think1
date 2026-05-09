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
      title="资产（我的）"
      @left-click="goHome"
    />

    <view class="section">
      <text class="section-title">个人信息</text>
      <view class="profile-cell">
        <UserProfileNav ref="userProfileRef" />
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

      <text class="section-title">游戏资产</text>
      <!-- 每日提醒开关 -->
      <view class="cell cell--reminder" @click="toggleDailyReminder">
        <text class="cell-icon">🔔</text>
        <view class="cell-main">
          <text class="cell-text">每日领奖提醒</text>
          <text class="cell-sub">开启后每日 12:00 收到微信提醒领取查看答案次数</text>
        </view>
        <switch
          :checked="dailyReminderOn"
          :disabled="dailyReminderLoading"
          color="#10b981"
          @change.stop="toggleDailyReminder"
          style="transform: scale(0.8);"
        />
      </view>
      <view class="cell cell--quota" @click="showHintQuotaTip">
        <view class="cell-quota-head">
          <text class="cell-icon">🎯</text>
          <view class="cell-main">
            <text class="cell-text">答案次数</text>
            <text class="cell-sub">点「答案」每次扣 1，分享可增加，单日上限 {{ hintAnswerShareDailyMax }} 次</text>
          </view>
        </view>
        <view class="quota-strip">
          <view class="quota-col">
            <text class="quota-col-label">剩余</text>
            <view class="quota-col-value">
              <text class="quota-num">{{ hintAnswerQuota }}</text>
              <text class="quota-unit">次</text>
            </view>
          </view>
          <view class="quota-strip-divider" />
          <view class="quota-col quota-col--muted">
            <text class="quota-col-label">历史累计</text>
            <view class="quota-col-value">
              <text class="quota-num quota-num--muted">{{ hintAnswerTotalUsed }}</text>
              <text class="quota-unit quota-unit--muted">次</text>
            </view>
          </view>
        </view>
      </view>
      <view class="cell" @click="goLevels">
        <text class="cell-icon">🧩</text>
        <text class="cell-text">我的关卡</text>
        <text class="cell-arrow">›</text>
      </view>
      <text class="section-title">其他</text>
      <view class="cell" @click="goFeedback">
        <text class="cell-icon">📝</text>
        <text class="cell-text">意见反馈</text>
        <text class="cell-arrow">›</text>
      </view>
    </view>

    <!-- <text class="hint">头像与昵称与首页顶部一致，修改后会同步保存。</text> -->

    <view
      v-if="hintQuotaTipVisible"
      class="quota-tip-mask"
      @click="dismissHintQuotaTip"
    >
      <view class="quota-tip-card" @click.stop>
        <text class="quota-tip-title">答案次数说明</text>
        <view class="quota-tip-body">
          <text class="quota-tip-line quota-tip-line--lead">剩余：当前可用次数。</text>
          <text class="quota-tip-line">历史累计：自统计上线后，你累计使用「答案」的总次数。</text>
          <text class="quota-tip-line">分享给好友可增加剩余次数。</text>
        </view>
        <view class="quota-tip-btn" @click="dismissHintQuotaTip">
          <text class="quota-tip-btn-text">知道了</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { onShow, onShareAppMessage, onShareTimeline } from '@dcloudio/uni-app'
import { wechatLogin } from '../../utils/auth'
import UserProfileNav from '../../components/UserProfileNav.vue'
import PunPageNavBar from '../../components/PunPageNavBar.vue'
import { useNavBar } from '../../composables/useNavBar'
import { usePunShareReward } from '../../composables/usePunShareReward'
import { SHARE_SUCCESS_THRESHOLD_MS } from '../../utils/punPlayShared'
import { api } from '../../utils/api'
import { REWARDED_VIDEO_AD_UNIT_ID } from '../../constants/rewardedVideoAd'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()
const userProfileRef = ref(null)
const hintAnswerQuota = ref(0)
const hintAnswerTotalUsed = ref(0)
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
const hintQuotaTipVisible = ref(false)
const dailyClaimLoading = ref(false)
const dailyAdTaskLoading = ref(false)
const dailyBattleTaskLoading = ref(false)
const dailyReminderOn = ref(false)
const dailyReminderLoading = ref(false)
const DAILY_REMIND_TEMPLATE_ID = 'rzQtKuen_qo-NivwIWEaQStbjgWZUokIKChNsZiVwfE'
const canClaimDailyReward = computed(
  () => dailyAnswerCount.value >= dailyAnswerRequired.value
)
const shareTaskClaimedAll = computed(
  () => hintAnswerShareDailyMax.value > 0 && shareDailyClaimed.value >= hintAnswerShareDailyMax.value
)
const shareTaskButtonText = computed(() => {
  if (shareTaskClaimedAll.value) return '已完成'
  if (shareClaimLoading.value) return '领取中...'
  if (shareDailyClaimed.value > 0) return '领取'
  return '分享'
})
const canClaimDailyBattleTask = computed(
  () => !dailyBattleTaskClaimed.value && dailyBattleCount.value >= dailyBattleRequired.value
)

let mineDailyVideoAd = null
let mineDailyVideoBusy = false
let shareLoadingTimer = null
const platform = (() => {
  try {
    return uni.getSystemInfoSync().uniPlatform || ''
  } catch {
    return ''
  }
})()
const isMiniProgram = platform.startsWith('mp-')

const { markShareIntent, withShareReward } = usePunShareReward(hintAnswerQuota, {
  mode: 'heuristic',
  shareSuccessThresholdMs: SHARE_SUCCESS_THRESHOLD_MS,
  showCancelToast: true,
  onClaimSuccess(payload) {
    if (shareLoadingTimer) {
      clearTimeout(shareLoadingTimer)
      shareLoadingTimer = null
    }
    shareClaimLoading.value = false
    const add = Number(payload && payload.added) > 0 ? Number(payload.added) : 1
    const max = Math.max(0, Math.floor(hintAnswerShareDailyMax.value || 0))
    shareDailyClaimed.value = Math.max(0, Math.min(max, Math.floor(shareDailyClaimed.value + add)))
    refreshHintAnswerQuota()
  },
})

onShareAppMessage(() => withShareReward({
  title: '我的 · 谐音梗图',
  path: '/pages/mine/mine',
}))

onShareTimeline(() => withShareReward({
  title: '我的 · 谐音梗图',
}))

onShow(async () => {
  updateLaunchEntryFlags()
  // 先读 storage，立即展示最新头像/昵称，不等待登录网络请求
  userProfileRef.value?.loadUserInfo?.()
  refreshHintAnswerQuota()
  try {
    await wechatLogin()
  } catch (e) {
    console.warn('wechatLogin 失败', e)
  }
  // 登录完成后再刷一次，确保与服务端同步（如多端修改过昵称等）
  userProfileRef.value?.loadUserInfo?.()
})

function goFeedback() {
  uni.navigateTo({ url: '/pages/feedback/feedback' })
}

function goLevels() {
  uni.navigateTo({ url: '/pages/levels/levels' })
}

function goHome() {
  uni.reLaunch({ url: '/pages/index/index' })
}

function showHintQuotaTip() {
  hintQuotaTipVisible.value = true
}

function dismissHintQuotaTip() {
  hintQuotaTipVisible.value = false
}

/** 当前冷启动场景是否为微信「我的小程序」或抖音「我的-收藏」入口（与后端校验一致） */
function updateLaunchEntryFlags() {
  launchFromMyMiniOrCollect.value = isLaunchFromMyMiniOrCollectScene()
}

function isLaunchFromMyMiniOrCollectScene() {
  try {
    // getEnterOptionsSync 支持热启动场景，getLaunchOptionsSync 仅冷启动
    const enterOpts = typeof uni.getEnterOptionsSync === 'function' ? uni.getEnterOptionsSync() : null
    const launchOpts = typeof uni.getLaunchOptionsSync === 'function' ? uni.getLaunchOptionsSync() : null
    const scenes = [enterOpts?.scene, launchOpts?.scene].filter(Boolean)
    if (scenes.length === 0) return false
    // 微信：1103（发现栏-我的小程序）、1104（聊天下拉-我的小程序）
    // 抖音：21003 / 021003（我的收藏）
    const validScenes = [1103, 1104, 21003]
    for (const scene of scenes) {
      const n = Number(scene)
      if (!Number.isNaN(n) && validScenes.includes(n)) return true
      if (String(scene) === '021003') return true
    }
    return false
  } catch {
    return false
  }
}

function ensureMineDailyVideoAd() {
  if (mineDailyVideoAd) {
    return mineDailyVideoAd
  }
  const creator = typeof wx !== 'undefined' && typeof wx.createRewardedVideoAd === 'function'
    ? wx.createRewardedVideoAd
    : (typeof tt !== 'undefined' && typeof tt.createRewardedVideoAd === 'function'
      ? tt.createRewardedVideoAd
      : null)
  if (!creator) {
    return null
  }
  try {
    mineDailyVideoAd = creator({ adUnitId: REWARDED_VIDEO_AD_UNIT_ID })
    mineDailyVideoAd.onError(() => {
      // 广告加载失败静默处理，避免 DevTools 报 timeout
    })
  } catch (e) {
    // 广告创建失败（如 DevTools 环境），静默降级
    mineDailyVideoAd = null
  }
  return mineDailyVideoAd
}

function watchDailyTaskAd() {
  if (mineDailyVideoBusy) {
    return Promise.resolve(false)
  }
  const ad = ensureMineDailyVideoAd()
  if (!ad) {
    uni.showToast({ title: '当前环境不支持激励视频', icon: 'none' })
    return Promise.resolve(false)
  }
  mineDailyVideoBusy = true
  return new Promise((resolve) => {
    let settled = false
    const finish = (ok) => {
      if (settled) return
      settled = true
      mineDailyVideoBusy = false
      resolve(ok)
    }

    const onClose = (res) => {
      try {
        ad.offClose(onClose)
      } catch (_) {}
      finish(!!(res && res.isEnded))
    }
    function loadAdWithTimeout(adInstance, timeoutMs = 10000) {
      return new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('广告加载超时')), timeoutMs)
        adInstance
          .load()
          .then(() => { clearTimeout(timer); resolve() })
          .catch((err) => { clearTimeout(timer); reject(err) })
      })
    }

    ad.onClose(onClose)
    ad.show()
      .catch(() => loadAdWithTimeout(ad, 10000).then(() => ad.show()))
      .catch((err) => {
        try { ad.offClose(onClose) } catch (_) {}
        const msg = err.message === '广告加载超时' ? '广告加载超时，请稍后重试' : '广告加载失败，请稍后重试'
        uni.showToast({ title: msg, icon: 'none' })
        finish(false)
      })
  })
}

async function refreshHintAnswerQuota() {
  try {
    const data = await api.getLevelProgress({ gameTier: 'mid' })
    if (data && typeof data.hintAnswerQuota === 'number') {
      hintAnswerQuota.value = data.hintAnswerQuota
    }
    if (data && typeof data.hintAnswerTotalUsed === 'number') {
      hintAnswerTotalUsed.value = data.hintAnswerTotalUsed
    }
    if (data && typeof data.hintAnswerShareDailyMax === 'number' && data.hintAnswerShareDailyMax > 0) {
      hintAnswerShareDailyMax.value = Math.floor(data.hintAnswerShareDailyMax)
    }
    if (data && typeof data.hintAnswerShareDailyClaimed === 'number' && data.hintAnswerShareDailyClaimed >= 0) {
      const max = Math.max(0, Math.floor(data.hintAnswerShareDailyMax ?? hintAnswerShareDailyMax.value))
      shareDailyClaimed.value = Math.max(0, Math.min(max, Math.floor(data.hintAnswerShareDailyClaimed)))
    }
    if (data && typeof data.dailyAnswerRequired === 'number' && data.dailyAnswerRequired > 0) {
      dailyAnswerRequired.value = Math.floor(data.dailyAnswerRequired)
    }
    if (data && typeof data.dailyAnswerCount === 'number' && data.dailyAnswerCount >= 0) {
      dailyAnswerCount.value = Math.max(0, Math.floor(data.dailyAnswerCount))
    }
    if (data && typeof data.dailyNoonTaskClaimed !== 'undefined') {
      dailyNoonTaskClaimed.value = Number(data.dailyNoonTaskClaimed) > 0
    }
    if (data && typeof data.dailyAdTaskCount === 'number' && data.dailyAdTaskCount >= 0) {
      dailyAdTaskCount.value = Math.max(0, Math.floor(data.dailyAdTaskCount))
    }
    if (data && typeof data.dailyBattleRequired === 'number' && data.dailyBattleRequired > 0) {
      dailyBattleRequired.value = Math.max(1, Math.floor(data.dailyBattleRequired))
    }
    if (data && typeof data.dailyBattleCount === 'number' && data.dailyBattleCount >= 0) {
      dailyBattleCount.value = Math.max(0, Math.floor(data.dailyBattleCount))
    }
    if (data && typeof data.dailyBattleTaskClaimed !== 'undefined') {
      dailyBattleTaskClaimed.value = Number(data.dailyBattleTaskClaimed) > 0
    }
    if (data && typeof data.avatarTaskClaimed !== 'undefined') {
      avatarTaskClaimed.value = Number(data.avatarTaskClaimed) > 0
    }
    if (data && typeof data.nicknameTaskClaimed !== 'undefined') {
      nicknameTaskClaimed.value = Number(data.nicknameTaskClaimed) > 0
    }
    if (data && typeof data.myMiniProgramTaskClaimed !== 'undefined') {
      myMiniProgramTaskClaimed.value = Number(data.myMiniProgramTaskClaimed) > 0
    }
    if (data && typeof data.dailyReminderSubscribed !== 'undefined') {
      dailyReminderOn.value = Number(data.dailyReminderSubscribed) > 0
    }
  } catch {
    // 忽略未登录场景
  }
}

async function claimDailyNoonReward() {
  if (dailyClaimLoading.value || dailyNoonTaskClaimed.value) return
  dailyClaimLoading.value = true
  try {
    const data = await api.claimReward({
      type: 'daily_noon_hint_5',
      add: 5,
    })
    if (typeof data.hintAnswerQuota === 'number') {
      hintAnswerQuota.value = data.hintAnswerQuota
    }
    dailyNoonTaskClaimed.value = true
    uni.showToast({ title: `领取成功 +${data.added || 5}`, icon: 'none' })
    refreshHintAnswerQuota()
  } catch (e) {
    uni.showToast({ title: e.message || '领取失败', icon: 'none' })
  } finally {
    dailyClaimLoading.value = false
  }
}

function onDailyRewardAction() {
  if (dailyNoonTaskClaimed.value) return
  if (!canClaimDailyReward.value) {
    goHome()
    return
  }
  claimDailyNoonReward()
}

async function claimDailyAdTask() {
  if (dailyAdTaskLoading.value) return
  if (!isMiniProgram) {
    uni.showToast({ title: '仅小程序支持看广告领取', icon: 'none' })
    return
  }
  if (!ensureMineDailyVideoAd()) {
    uni.showToast({ title: '当前平台暂不支持激励视频', icon: 'none' })
  return
  }
  dailyAdTaskLoading.value = true
  try {
    const watched = await watchDailyTaskAd()
    if (!watched) {
      uni.showToast({ title: '请完整观看广告后领取', icon: 'none' })
      return
    }
    const data = await api.claimReward({
      type: 'daily_watch_ad_hint_1',
      add: 1,
    })
    if (typeof data.hintAnswerQuota === 'number') {
      hintAnswerQuota.value = data.hintAnswerQuota
    }
    const add = Number(data && data.added) > 0 ? Number(data.added) : 1
    dailyAdTaskCount.value = Math.max(0, dailyAdTaskCount.value + add)
    uni.showToast({ title: `领取成功 +${data.added || 1}`, icon: 'none' })
    refreshHintAnswerQuota()
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
    const data = await api.claimReward({
      type: 'daily_battle_3_hint_3',
      add: 3,
    })
    if (typeof data.hintAnswerQuota === 'number') {
      hintAnswerQuota.value = data.hintAnswerQuota
    }
    dailyBattleTaskClaimed.value = true
    uni.showToast({ title: `领取成功 +${data.added || 3}`, icon: 'none' })
    refreshHintAnswerQuota()
  } catch (e) {
    uni.showToast({ title: e.message || '领取失败', icon: 'none' })
  } finally {
    dailyBattleTaskLoading.value = false
  }
}

function onDailyBattleTaskAction() {
  if (dailyBattleTaskClaimed.value) return
  if (!canClaimDailyBattleTask.value) {
    uni.navigateTo({ url: '/pages/battleRoom/battleRoom' })
    return
  }
  claimDailyBattleTask()
}

async function claimAvatarTask() {
  if (avatarTaskClaimLoading.value || avatarTaskClaimed.value) return
  avatarTaskClaimLoading.value = true
  try {
    const data = await api.claimReward({ type: 'permanent_set_avatar' })
    if (typeof data.hintAnswerQuota === 'number') {
      hintAnswerQuota.value = data.hintAnswerQuota
    }
    avatarTaskClaimed.value = true
    uni.showToast({ title: `领取成功 +${data.added || 3}`, icon: 'none' })
  } catch (e) {
    uni.showToast({ title: e.message || '领取失败', icon: 'none' })
  } finally {
    avatarTaskClaimLoading.value = false
  }
}

async function claimMyMiniProgramTask() {
  if (myMiniProgramTaskLoading.value || myMiniProgramTaskClaimed.value) return
  updateLaunchEntryFlags()
  if (!launchFromMyMiniOrCollect.value) {
    uni.showToast({
      title: '请先添加到「我的小程序/收藏」，关闭后从该入口重新进入',
      icon: 'none',
    })
    return
  }
  myMiniProgramTaskLoading.value = true
  try {
    let launchScene
    try {
      // 优先取热启动场景值
      const enter = typeof uni.getEnterOptionsSync === 'function' ? uni.getEnterOptionsSync() : null
      launchScene = enter?.scene ?? uni.getLaunchOptionsSync()?.scene
    } catch {
      launchScene = undefined
    }
    const data = await api.claimReward({
      type: 'permanent_my_mini_program_hint_3',
      launchScene,
    })
    if (typeof data.hintAnswerQuota === 'number') {
      hintAnswerQuota.value = data.hintAnswerQuota
    }
    myMiniProgramTaskClaimed.value = true
    uni.showToast({ title: `领取成功 +${data.added || 3}`, icon: 'none' })
    refreshHintAnswerQuota()
  } catch (e) {
    uni.showToast({ title: e.message || '领取失败', icon: 'none' })
  } finally {
    myMiniProgramTaskLoading.value = false
  }
}

async function claimNicknameTask() {
  if (nicknameTaskClaimLoading.value || nicknameTaskClaimed.value) return
  nicknameTaskClaimLoading.value = true
  try {
    const data = await api.claimReward({ type: 'permanent_set_nickname' })
    if (typeof data.hintAnswerQuota === 'number') {
      hintAnswerQuota.value = data.hintAnswerQuota
    }
    nicknameTaskClaimed.value = true
    uni.showToast({ title: `领取成功 +${data.added || 3}`, icon: 'none' })
  } catch (e) {
    uni.showToast({ title: e.message || '领取失败', icon: 'none' })
  } finally {
    nicknameTaskClaimLoading.value = false
  }
}

function onShareTaskIntent() {
  if (!isMiniProgram) {
    uni.showToast({ title: '仅小程序支持分享领奖', icon: 'none' })
    return
  }
  if (shareTaskClaimedAll.value) return
  shareClaimLoading.value = true
  if (shareLoadingTimer) {
    clearTimeout(shareLoadingTimer)
    shareLoadingTimer = null
  }
  // 兜底：取消分享/异常时自动回到可点击态
  shareLoadingTimer = setTimeout(() => {
    shareLoadingTimer = null
    shareClaimLoading.value = false
  }, 6000)
  // 记录分享意图；实际领奖由 usePunShareReward 按“后台停留 >= 2000ms”判定
  markShareIntent()
}

async function toggleDailyReminder() {
  if (dailyReminderLoading.value) return
  if (dailyReminderOn.value) {
    // 关闭提醒
    dailyReminderLoading.value = true
    try {
      await api.saveSubscribe(DAILY_REMIND_TEMPLATE_ID, 'reject')
      dailyReminderOn.value = false
      uni.showToast({ title: '已关闭每日提醒', icon: 'none' })
    } catch (e) {
      uni.showToast({ title: e.message || '操作失败', icon: 'none' })
    } finally {
      dailyReminderLoading.value = false
    }
    return
  }
  // 开启提醒：先调用微信订阅消息授权
  // #ifdef MP-WEIXIN
  dailyReminderLoading.value = true
  try {
    const res = await new Promise((resolve, reject) => {
      wx.requestSubscribeMessage({
        tmplIds: [DAILY_REMIND_TEMPLATE_ID],
        success: (r) => resolve(r),
        fail: (err) => reject(err),
      })
    })
    const status = res[DAILY_REMIND_TEMPLATE_ID]
    if (status === 'accept') {
      await api.saveSubscribe(DAILY_REMIND_TEMPLATE_ID, 'accept')
      dailyReminderOn.value = true
      uni.showToast({ title: '已开启每日提醒', icon: 'none' })
    } else {
      uni.showToast({ title: '需要允许通知才能开启提醒', icon: 'none' })
    }
  } catch (e) {
    // 用户点了取消或授权弹窗关闭
    uni.showToast({ title: '请允许订阅消息以开启提醒', icon: 'none' })
  } finally {
    dailyReminderLoading.value = false
  }
  // #endif
  // #ifdef MP-TOUTIAO
  dailyReminderLoading.value = true
  try {
    await api.saveSubscribe(DAILY_REMIND_TEMPLATE_ID, 'accept')
    dailyReminderOn.value = true
    uni.showToast({ title: '已开启每日提醒', icon: 'none' })
  } catch (e) {
    uni.showToast({ title: e.message || '操作失败', icon: 'none' })
  } finally {
    dailyReminderLoading.value = false
  }
  // #endif
  // #ifndef MP-WEIXIN
  // #ifndef MP-TOUTIAO
  uni.showToast({ title: '仅小程序支持订阅提醒', icon: 'none' })
  // #endif
  // #endif
}

onBeforeUnmount(() => {
  if (shareLoadingTimer) {
    clearTimeout(shareLoadingTimer)
    shareLoadingTimer = null
  }
  shareClaimLoading.value = false
  if (mineDailyVideoAd && typeof mineDailyVideoAd.destroy === 'function') {
    try {
      mineDailyVideoAd.destroy()
    } catch (_) {}
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
  margin: 0 auto;
}

.profile-cell {
  margin-bottom: 16rpx;
  padding: 24rpx;
  background: rgba(255, 255, 255, 0.78);
  border-radius: 24rpx;
  border: 2rpx solid $pt-border;
  box-shadow: 0 6rpx 18rpx $pt-shadow-soft;
}

.section-title {
  display: block;
  font-size: 26rpx;
  font-weight: 700;
  color: $pt-muted;
  margin: 20rpx 0;
  letter-spacing: 0.06em;
}

.cell {
  display: flex;
  align-items: center;
  gap: 20rpx;
  padding: 28rpx 24rpx;
  margin-bottom: 20rpx;
  background: rgba(255, 255, 255, 0.78);
  border-radius: 24rpx;
  border: 2rpx solid $pt-border;
  box-shadow: 0 6rpx 18rpx $pt-shadow-soft;
}

.cell--quota {
  flex-direction: column;
  align-items: stretch;
  gap: 20rpx;
  padding-top: 24rpx;
  padding-bottom: 22rpx;
}

.cell--reminder {
  margin-bottom: 20rpx;
}

.cell--daily {
  padding-top: 24rpx;
  padding-bottom: 24rpx;
  gap: 18rpx;
}

.cell-daily-head {
  display: flex;
  align-items: flex-start;
  gap: 20rpx;
  width: 100%;
}

.daily-claim-btn {
  width: 35%;
  height: 60rpx;
  line-height: 60rpx;
  border-radius: 18rpx;
  border: none;
  background: linear-gradient(180deg, #ffd98f 0%, #f7be60 100%);
  color: #754400;
  font-size: 24rpx;
}

.daily-claim-btn::after {
  border: none;
}

.daily-claim-btn--disabled,
.daily-claim-btn[disabled] {
  background: linear-gradient(180deg, #e5e7eb 0%, #d1d5db 100%) !important;
  color: #6b7280 !important;
}

.daily-claim-btn--share {
  background: linear-gradient(180deg, #c9e6ff 0%, #9ccfff 100%);
  color: #1f4f7a;
}

// ── 任务卡片区 ──────────────────────────────────────────
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

.task-section-icon {
  font-size: 30rpx;
  line-height: 1;
}

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

.task-row-icon {
  font-size: 36rpx;
  line-height: 1;
  margin-top: 2rpx;
  flex-shrink: 0;
}

.task-row-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6rpx;
}

.task-row-name {
  font-size: 27rpx;
  font-weight: 700;
  color: $pt-text;
  line-height: 1.3;
}

.task-row-sub {
  font-size: 21rpx;
  color: $pt-muted;
  line-height: 1.45;
}

.task-row-reward {
  font-size: 21rpx;
  font-weight: 700;
  color: #d97706;
}

.task-row-progress-wrap {
  display: flex;
  flex-direction: column;
  gap: 6rpx;
}

.task-row-progress-bar {
  width: 100%;
  height: 6rpx;
  border-radius: 6rpx;
  background: rgba(0, 0, 0, 0.08);
  overflow: hidden;
}

.task-row-progress-fill {
  height: 100%;
  border-radius: 6rpx;
  background: linear-gradient(90deg, #86efac 0%, #22c55e 100%);
  transition: width 0.3s ease;
}

.task-divider {
  height: 1rpx;
  margin: 0 24rpx;
  background: rgba(0, 0, 0, 0.05);
}

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

.task-btn::after {
  border: none;
}

.task-btn--permanent {
  background: linear-gradient(135deg, #c4b5fd 0%, #a78bfa 100%);
  color: #4c1d95;
}

.task-btn--daily {
  background: linear-gradient(135deg, #fde68a 0%, #f59e0b 100%);
  color: #78350f;
}

.task-btn--share {
  background: linear-gradient(135deg, #bae6fd 0%, #38bdf8 100%);
  color: #0c4a6e;
}

.task-btn--goto {
  background: rgba(0, 0, 0, 0.06);
  color: $pt-muted;
}

.task-btn--done {
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  color: #065f46;
}

.task-btn[disabled].task-btn--done {
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%) !important;
  color: #065f46 !important;
}

.task-btn[disabled]:not(.task-btn--done) {
  background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%) !important;
  color: #9ca3af !important;
}

// ────────────────────────────────────────────────────────

.cell-quota-head {
  display: flex;
  align-items: flex-start;
  gap: 20rpx;
}
.cell:active {
  opacity: 0.92;
  transform: scale(0.99);
}

.cell-icon {
  font-size: 40rpx;
  line-height: 1;
}

.cell-text {
  flex: 1;
  font-size: 28rpx;
  font-weight: 700;
  color: $pt-text;
}

.cell-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6rpx;
}

.cell-sub {
  font-size: 22rpx;
  line-height: 1.45;
  color: $pt-muted;
}

.quota-strip {
  display: flex;
  flex-direction: row;
  align-items: stretch;
  width: 100%;
  padding: 18rpx 16rpx;
  box-sizing: border-box;
  border-radius: 18rpx;
  background: rgba(234, 246, 249, 0.65);
  border: 1rpx solid rgba(169, 201, 238, 0.35);
}

.quota-col {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10rpx;
}

.quota-col--muted .quota-col-label {
  opacity: 0.88;
}

.quota-col-label {
  font-size: 22rpx;
  color: $pt-muted;
  font-weight: 700;
  letter-spacing: 0.04em;
}

.quota-col-value {
  display: flex;
  align-items: baseline;
  justify-content: center;
  gap: 4rpx;
}

.quota-strip-divider {
  width: 1rpx;
  align-self: stretch;
  min-height: 72rpx;
  background: rgba(169, 201, 238, 0.45);
  flex-shrink: 0;
}

.quota-num {
  font-size: 40rpx;
  line-height: 1;
  font-weight: 900;
  color: #16a34a;
  font-variant-numeric: tabular-nums;
}

.quota-num--muted {
  font-size: 34rpx;
  font-weight: 800;
  color: #64748b;
}

.quota-unit {
  font-size: 22rpx;
  color: #16a34a;
  font-weight: 700;
}

.quota-unit--muted {
  font-size: 22rpx;
  color: #64748b;
  font-weight: 600;
}

.cell-arrow {
  flex-shrink: 0;
  margin-left: auto;
  font-size: 36rpx;
  color: $pt-muted;
  font-weight: 300;
}

.hint {
  position: relative;
  z-index: 2;
  display: block;
  margin-top: 32rpx;
  text-align: center;
  font-size: 22rpx;
  color: $pt-muted;
  line-height: 1.5;
  padding: 0 20rpx;
}

.quota-tip-mask {
  position: fixed;
  inset: 0;
  z-index: 300;
  background: rgba(90, 120, 130, 0.25);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 48rpx;
  box-sizing: border-box;
}

.quota-tip-card {
  width: 100%;
  max-width: 600rpx;
  background: rgba(255, 255, 255, 0.96);
  border-radius: 32rpx;
  padding: 36rpx 32rpx 28rpx;
  box-shadow: 0 16rpx 40rpx rgba(169, 201, 238, 0.35);
  border: 3rpx solid rgba(169, 201, 238, 0.4);
}

.quota-tip-title {
  display: block;
  font-size: 32rpx;
  font-weight: 800;
  color: #6fb868;
  text-align: center;
  margin-bottom: 24rpx;
}

.quota-tip-body {
  display: flex;
  flex-direction: column;
  gap: 20rpx;
  margin-bottom: 28rpx;
}

.quota-tip-line {
  display: block;
  font-size: 26rpx;
  line-height: 1.55;
  color: #6a7f8c;
  font-weight: 600;
}

.quota-tip-line--lead {
  font-size: 28rpx;
  font-weight: 700;
  color: #5a6d7a;
}

.quota-tip-btn {
  align-self: center;
  padding: 18rpx 72rpx;
  background: linear-gradient(180deg, #a3dd9c 0%, #91d58b 100%);
  border-radius: 100rpx;
  border: 3rpx solid rgba(255, 255, 255, 0.5);
  box-shadow: 0 6rpx 18rpx rgba(145, 213, 139, 0.35);
  text-align: center;
}

.quota-tip-btn:active {
  opacity: 0.92;
  transform: scale(0.98);
}

.quota-tip-btn-text {
  font-size: 28rpx;
  font-weight: 700;
  color: #fff;
  text-align: center;
}
</style>

