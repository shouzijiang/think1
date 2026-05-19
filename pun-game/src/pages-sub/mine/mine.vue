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
      title="我的资产（个人中心）"
      @left-click="goHome"
    />

    <view class="section">
      <text class="section-title">个人信息</text>
      <view class="profile-cell">
        <UserProfileNav ref="userProfileRef" />
      </view>
      <!-- 任务中心入口 -->
      <view
        class="cell cell--task-entry"
        hover-class="cell--hover"
        :hover-start-time="20"
        :hover-stay-time="100"
        @click="goTasks"
      >
        <text class="cell-icon">🎯</text>
        <view class="cell-main">
          <text class="cell-text">任务中心</text>
          <text class="cell-sub">每日任务、永久任务，赚取答案次数</text>
        </view>
        <text class="cell-arrow">›</text>
      </view>

      <text class="section-title">游戏资产</text>
      <!-- 每日提醒开关 -->
      <view
        class="cell cell--reminder"
        hover-class="cell--hover"
        :hover-start-time="20"
        :hover-stay-time="100"
        @click="toggleDailyReminder"
      >
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
      <view
        class="cell cell--quota"
        hover-class="cell--hover"
        :hover-start-time="20"
        :hover-stay-time="100"
        @click="showHintQuotaTip"
      >
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
      <view
        class="cell"
        hover-class="cell--hover"
        :hover-start-time="20"
        :hover-stay-time="100"
        @click="goLevels"
      >
        <text class="cell-icon">📋</text>
        <text class="cell-text">我的关卡</text>
        <text class="cell-arrow">›</text>
      </view>
      <text class="section-title">其他</text>
      <view
        class="cell"
        hover-class="cell--hover"
        :hover-start-time="20"
        :hover-stay-time="100"
        @click="goFeedback"
      >
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
        <view
          class="quota-tip-btn"
          hover-class="quota-tip-btn--hover"
          :hover-start-time="20"
          :hover-stay-time="100"
          @click="dismissHintQuotaTip"
        >
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
import { api } from '../../utils/api'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()
const userProfileRef = ref(null)
const hintAnswerQuota = ref(0)
const hintAnswerTotalUsed = ref(0)
const hintAnswerShareDailyMax = ref(5)
const hintQuotaTipVisible = ref(false)
const dailyReminderOn = ref(false)
const dailyReminderLoading = ref(false)
const DAILY_REMIND_TEMPLATE_ID = 'rzQtKuen_qo-NivwIWEaQStbjgWZUokIKChNsZiVwfE'

const platform = (() => {
  try {
    return (uni.getAppBaseInfo?.() ?? uni.getSystemInfoSync()).uniPlatform || ''
  } catch {
    return ''
  }
})()
const isMiniProgram = platform.startsWith('mp-')

onShow(async () => {
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
  uni.navigateTo({ url: '/pages-sub/feedback/feedback' })
}

function goLevels() {
  uni.navigateTo({ url: '/pages-sub/levels/levels' })
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

function goTasks() {
  uni.navigateTo({ url: '/pages-sub/tasks/tasks' })
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
    if (data && typeof data.dailyReminderSubscribed !== 'undefined') {
      dailyReminderOn.value = Number(data.dailyReminderSubscribed) > 0
    }
  } catch {
    // 忽略未登录场景
  }
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

.cell--task-entry {
  margin-bottom: 20rpx;
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
.cell--hover {
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
  color: #587186;
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

.quota-tip-btn--hover {
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

