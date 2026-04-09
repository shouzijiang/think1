<template>
  <view v-if="show" :class="['pun-pass', `pun-pass--${variant}`]">
    <view class="pun-pass__content">
      <template v-if="variant === 'battle'">
        <text class="pun-pass__icon pun-pass__icon--battle">🎉</text>
        <text class="pun-pass__title pun-pass__title--battle">回答正确~</text>
      </template>
      <template v-else>
        <view class="pun-pass__icon-wrap">
          <text class="pun-pass__icon">🎉</text>
        </view>
        <text class="pun-pass__title">回答正确~</text>
      </template>
    </view>
  </view>
</template>

<script setup>
defineProps({
  show: { type: Boolean, default: false },
  /** rich：完整弹入/弹跳（与 play、playMid 一致）；plain：静态卡片（与 playXhs 一致）；battle：对战页白底样式 */
  variant: {
    type: String,
    default: 'rich',
    validator: (v) => ['rich', 'plain', 'battle'].includes(v),
  },
})
</script>

<style lang="scss" scoped>
/* ---------- rich：play / playMid ---------- */
.pun-pass--rich {
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  animation: pun-pass-fade-in 0.3s ease-out forwards;
}
.pun-pass--rich .pun-pass__content {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: linear-gradient(145deg, #ffffff 0%, #fdfbfb 100%);
  padding: 60rpx 80rpx;
  border-radius: 40rpx;
  box-shadow: 0 20rpx 60rpx rgba(0, 0, 0, 0.2);
  transform: scale(0.8);
  opacity: 0;
  animation: pun-pass-pop-in 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
  animation-delay: 0.1s;
}
.pun-pass--rich .pun-pass__icon-wrap {
  width: 160rpx;
  height: 160rpx;
  background: #f0fdf4;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 32rpx;
  box-shadow: inset 0 0 20rpx rgba(74, 222, 128, 0.2);
}
.pun-pass--rich .pun-pass__icon {
  font-size: 80rpx;
  animation: pun-pass-bounce 1s infinite;
}
.pun-pass--rich .pun-pass__title {
  font-size: 48rpx;
  font-weight: 900;
  color: #166534;
  margin-bottom: 16rpx;
}

/* ---------- plain：playXhs（无 overlay/content 入场与弹跳） ---------- */
.pun-pass--plain {
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
}
.pun-pass--plain .pun-pass__content {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: linear-gradient(145deg, #ffffff 0%, #fdfbfb 100%);
  padding: 60rpx 80rpx;
  border-radius: 40rpx;
}
.pun-pass--plain .pun-pass__icon-wrap {
  width: 160rpx;
  height: 160rpx;
  background: #f0fdf4;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 32rpx;
}
.pun-pass--plain .pun-pass__icon {
  font-size: 80rpx;
}
.pun-pass--plain .pun-pass__title {
  font-size: 48rpx;
  font-weight: 900;
  color: #166534;
}

/* ---------- battle：对战页 ---------- */
.pun-pass--battle {
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
}
.pun-pass--battle .pun-pass__content {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: #fff;
  padding: 60rpx;
  border-radius: 40rpx;
}
.pun-pass--battle .pun-pass__icon--battle {
  font-size: 80rpx;
}
.pun-pass--battle .pun-pass__title--battle {
  font-size: 40rpx;
  font-weight: bold;
  margin-top: 20rpx;
}

@keyframes pun-pass-fade-in {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
@keyframes pun-pass-pop-in {
  from {
    opacity: 0;
    transform: scale(0.8) translateY(40rpx);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}
@keyframes pun-pass-bounce {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-16rpx);
  }
}
</style>
