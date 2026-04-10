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
            <text class="cell-sub">点「答案」每次扣 1，分享可补充</text>
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
          <text class="quota-tip-line">分享至朋友圈/微信可增加剩余次数。</text>
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
const hintQuotaTipVisible = ref(false)

// #ifdef MP-WEIXIN
useWechatPageShare('我的 · 谐音梗图', hintAnswerQuota)
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
  } catch {
    // 忽略未登录场景
  }
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
  max-width: 560rpx;
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
