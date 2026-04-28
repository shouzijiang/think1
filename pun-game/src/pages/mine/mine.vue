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
      <text class="section-title">游戏资产</text>
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
      <text class="section-title">每日任务</text>
      <view class="cell cell--daily">
        <view class="cell-daily-head">
          <text class="cell-icon">🎁</text>
          <view class="cell-main">
            <text class="cell-text">每日12点领5次答案</text>
            <text class="cell-sub">需授权订阅通知，今日限领一次</text>
          </view>
        </view>
        <button
          class="daily-claim-btn"
          type="primary"
          :disabled="dailyClaimLoading"
          @click.stop="claimDailyNoonReward"
        >
          {{ dailyClaimLoading ? '领取中...' : '点击领取' }}
        </button>
      </view>
      <view class="cell cell--daily">
        <view class="cell-daily-head">
          <text class="cell-icon">📣</text>
          <view class="cell-main">
            <text class="cell-text">分享给好友领1次</text>
            <text class="cell-sub">今日已领取 {{ shareDailyClaimed }}/{{ hintAnswerShareDailyMax }} 次</text>
          </view>
        </view>
        <!-- #ifdef MP-WEIXIN -->
        <button
          class="daily-claim-btn daily-claim-btn--share"
          open-type="share"
          @click.stop="onShareTaskIntent"
        >
          分享给好友
        </button>
        <!-- #endif -->
        <!-- #ifndef MP-WEIXIN -->
        <button
          class="daily-claim-btn daily-claim-btn--share"
          @click.stop="onShareTaskIntent"
        >
          分享给好友
        </button>
        <!-- #endif -->
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
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { wechatLogin } from '../../utils/auth'
import UserProfileNav from '../../components/UserProfileNav.vue'
import PunPageNavBar from '../../components/PunPageNavBar.vue'
import { useNavBar } from '../../composables/useNavBar'
import { useWechatPageShare } from '../../composables/useWechatPageShare'
import { api } from '../../utils/api'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()
const userProfileRef = ref(null)
const hintAnswerQuota = ref(0)
const hintAnswerTotalUsed = ref(0)
const hintAnswerShareDailyMax = ref(5)
const shareDailyClaimed = ref(0)
const hintQuotaTipVisible = ref(false)
const dailyClaimLoading = ref(false)
const DAILY_NOON_TEMPLATE_ID = 'rzQtKuen_qo-NivwIWEaQStbjgWZUokIKChNsZiVwfE'
const DAILY_SUBSCRIBE_ACCEPT_KEY = `pun_daily_subscribe_accept_${DAILY_NOON_TEMPLATE_ID}`

// #ifdef MP-WEIXIN
useWechatPageShare('我的 · 谐音梗图', hintAnswerQuota, {
  onShareRewardSuccess(payload) {
    const add = Number(payload && payload.added) > 0 ? Number(payload.added) : 1
    const max = Math.max(0, Math.floor(hintAnswerShareDailyMax.value || 0))
    shareDailyClaimed.value = Math.max(0, Math.min(max, Math.floor(shareDailyClaimed.value + add)))
    refreshHintAnswerQuota()
  },
})
// #endif

onShow(async () => {
  // #ifdef MP-WEIXIN
  try {
    await wechatLogin()
  } catch (e) {
    console.warn('wechatLogin 失败', e)
  }
  // #endif
  userProfileRef.value?.loadUserInfo?.()
  refreshHintAnswerQuota()
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
  } catch {
    // 忽略未登录场景
  }
}

async function claimDailyNoonReward() {
  if (dailyClaimLoading.value) return
  dailyClaimLoading.value = true
  let subscribeStatus = 'accept'
  const acceptedBefore = !!uni.getStorageSync(DAILY_SUBSCRIBE_ACCEPT_KEY)

  async function requestSubscribeIfNeeded() {
    // #ifdef MP-WEIXIN
    if (acceptedBefore) return 'accept'
    const res = await new Promise((resolve, reject) => {
      wx.requestSubscribeMessage({
        tmplIds: [DAILY_NOON_TEMPLATE_ID],
        success: resolve,
        fail: reject,
      })
    })
    const status = String(res && res[DAILY_NOON_TEMPLATE_ID] ? res[DAILY_NOON_TEMPLATE_ID] : 'reject')
    if (status === 'accept') {
      uni.setStorageSync(DAILY_SUBSCRIBE_ACCEPT_KEY, 1)
    }
    return status
    // #endif
    // #ifndef MP-WEIXIN
    throw new Error('仅微信小程序支持该活动')
    // #endif
  }

  try {
    subscribeStatus = await requestSubscribeIfNeeded()
  } catch (e) {
    dailyClaimLoading.value = false
    uni.showToast({ title: e.message || '订阅授权失败，请稍后重试', icon: 'none' })
    return
  }

  try {
    let data
    try {
      data = await api.claimReward({
        type: 'daily_noon_hint_5',
        add: 5,
        subscribeStatus,
        templateId: DAILY_NOON_TEMPLATE_ID,
      })
    } catch (e) {
      // 本地已标记 accept 但服务端未记录时，补一次授权再重试
      if (acceptedBefore && String(e.message || '').includes('请先授权订阅通知')) {
        const latestStatus = await requestSubscribeIfNeeded()
        data = await api.claimReward({
          type: 'daily_noon_hint_5',
          add: 5,
          subscribeStatus: latestStatus,
          templateId: DAILY_NOON_TEMPLATE_ID,
        })
      } else {
        throw e
      }
    }

    if (typeof data.hintAnswerQuota === 'number') {
      hintAnswerQuota.value = data.hintAnswerQuota
    }
    uni.showToast({ title: `领取成功 +${data.added || 5}`, icon: 'none' })
    refreshHintAnswerQuota()
  } catch (e) {
    uni.showToast({ title: e.message || '领取失败', icon: 'none' })
  } finally {
    dailyClaimLoading.value = false
  }
}

function onShareTaskIntent() {
  // #ifndef MP-WEIXIN
  uni.showToast({ title: '仅微信小程序支持分享领奖', icon: 'none' })
  // #endif
}
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
  width: 50%;
  height: 74rpx;
  border-radius: 18rpx;
  border: none;
  background: linear-gradient(180deg, #ffd98f 0%, #f7be60 100%);
  color: #754400;
  font-size: 28rpx;
  font-weight: 800;
}

.daily-claim-btn::after {
  border: none;
}

.daily-claim-btn--share {
  background: linear-gradient(180deg, #c9e6ff 0%, #9ccfff 100%);
  color: #1f4f7a;
}

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
