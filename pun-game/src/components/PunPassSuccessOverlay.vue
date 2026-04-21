<template>
  <view
    v-if="props.show"
    :class="['pun-pass', `pun-pass--${props.variant}`, { 'pun-pass--interactive': props.showAction || props.tapAnywhere }]"
    @click="handleOverlayClick"
  >
    <view class="pun-pass__scene">
      <!-- 全屏底光 -->
      <view class="pun-pass__mesh" />
      <view class="pun-pass__vignette" />

      <!-- 中心扩散波纹 -->
      <view class="pun-pass__waves" aria-hidden="true">
        <view class="pun-pass__wave" />
        <view class="pun-pass__wave pun-pass__wave--d1" />
        <view class="pun-pass__wave pun-pass__wave--d2" />
      </view>

      <!-- 放射状短线（轻量「礼花」感，纯 CSS） -->
      <view class="pun-pass__rays" aria-hidden="true">
        <view v-for="i in 16" :key="'r' + i" class="pun-pass__ray" :style="rayStyle(i)" />
      </view>

      <!-- 飘落粒子 -->
      <view class="pun-pass__confetti" aria-hidden="true">
        <view v-for="i in 18" :key="'c' + i" class="pun-pass__confetti-piece" :style="confettiStyle(i)" />
      </view>

      <!-- 角标星光 -->
      <view class="pun-pass__stars" aria-hidden="true">
        <text class="pun-pass__star pun-pass__star--a">✨</text>
        <text class="pun-pass__star pun-pass__star--b">⭐</text>
        <text class="pun-pass__star pun-pass__star--c">🎉</text>
        <text class="pun-pass__star pun-pass__star--d">✨</text>
      </view>

      <!-- 主文案区：全屏重心，弱化「弹窗卡片」 -->
      <view class="pun-pass__hero">
        <template v-if="props.variant === 'battle'">
          <text class="pun-pass__kicker pun-pass__kicker--battle">Nice!</text>
          <text class="pun-pass__headline pun-pass__headline--battle">回答正确~</text>
          <text class="pun-pass__sub pun-pass__sub--battle">继续加油</text>
        </template>
        <template v-else-if="props.variant === 'plain'">
          <text class="pun-pass__kicker">闯关成功</text>
          <text class="pun-pass__headline">回答正确~</text>
          <text class="pun-pass__sub">太棒了</text>
        </template>
        <template v-else>
          <text class="pun-pass__kicker">闯关成功</text>
          <text class="pun-pass__headline">回答正确~</text>
          <text class="pun-pass__sub">继续下一关吧</text>
        </template>

        <button
          v-if="props.showAction"
          class="pun-pass__action"
          type="primary"
          :disabled="!canInteract"
          @click.stop="handleActionClick"
        >
          {{ props.actionText }}
        </button>
      </view>
    </view>
  </view>
</template>

<script setup>
import { ref, watch, onUnmounted } from 'vue'

/** 过关动画展示后延迟（毫秒），避免误触瞬间关闭 */
const ACTION_UNLOCK_MS = 1000

const props = defineProps({
  show: { type: Boolean, default: false },
  /** rich：经典绿调全屏；plain：小红书粉调；battle：对战蓝青调 */
  variant: {
    type: String,
    default: 'rich',
    validator: (v) => ['rich', 'plain', 'battle'].includes(v),
  },
  showAction: { type: Boolean, default: false },
  actionText: { type: String, default: '下一关' },
  tapAnywhere: { type: Boolean, default: false },
})
const emit = defineEmits(['action'])

const canInteract = ref(false)
let unlockTimer = null

watch(
  () => props.show,
  (visible) => {
    if (unlockTimer) {
      clearTimeout(unlockTimer)
      unlockTimer = null
    }
    if (visible) {
      canInteract.value = false
      unlockTimer = setTimeout(() => {
        unlockTimer = null
        canInteract.value = true
      }, ACTION_UNLOCK_MS)
    } else {
      canInteract.value = false
    }
  },
  { immediate: true },
)

onUnmounted(() => {
  if (unlockTimer) {
    clearTimeout(unlockTimer)
    unlockTimer = null
  }
})

function handleOverlayClick() {
  if (!props.showAction && !props.tapAnywhere) return
  if (!canInteract.value) return
  emit('action')
}

function handleActionClick() {
  if (!canInteract.value) return
  emit('action')
}

/** 放射短线角度 */
function rayStyle(i) {
  const deg = (i - 1) * 22.5
  return {
    transform: `rotate(${deg}deg)`,
  }
}

/** 彩色纸条初始水平位置与延迟 */
function confettiStyle(i) {
  const left = 5 + ((i * 17) % 90)
  const delay = ((i * 0.07) % 1).toFixed(2)
  const hue = (i * 47) % 360
  return {
    left: `${left}%`,
    animationDelay: `${delay}s`,
    backgroundColor: `hsla(${hue}, 85%, 62%, 0.95)`,
  }
}
</script>

<style lang="scss" scoped>
.pun-pass {
  position: fixed;
  inset: 0;
  z-index: 999;
  pointer-events: none;
}

.pun-pass--interactive {
  pointer-events: auto;
}

.pun-pass__scene {
  position: absolute;
  inset: 0;
  overflow: hidden;
  animation: pun-pass-scene-in 0.35s ease-out both;
}

@keyframes pun-pass-scene-in {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

/* —— 全屏渐变光 —— */
.pun-pass__mesh {
  position: absolute;
  inset: -20%;
  background: linear-gradient(
    130deg,
    rgba(34, 197, 94, 0.92) 0%,
    rgba(16, 185, 129, 0.88) 35%,
    rgba(250, 204, 21, 0.45) 70%,
    rgba(52, 211, 153, 0.75) 100%
  );
  background-size: 220% 220%;
  animation: pun-pass-mesh-move 3.5s ease-in-out infinite;
}

.pun-pass--plain .pun-pass__mesh {
  background: linear-gradient(
    135deg,
    rgba(244, 114, 182, 0.9) 0%,
    rgba(251, 113, 133, 0.85) 40%,
    rgba(253, 186, 116, 0.5) 100%
  );
  background-size: 200% 200%;
}

.pun-pass--battle .pun-pass__mesh {
  background: linear-gradient(
    135deg,
    rgba(56, 189, 248, 0.92) 0%,
    rgba(99, 102, 241, 0.82) 45%,
    rgba(45, 212, 191, 0.65) 100%
  );
  background-size: 210% 210%;
}

@keyframes pun-pass-mesh-move {
  0%,
  100% {
    transform: scale(1) translate3d(0, 0, 0);
    background-position: 0% 40%;
  }
  50% {
    transform: scale(1.06) translate3d(-2%, 1%, 0);
    background-position: 100% 60%;
  }
}

.pun-pass__vignette {
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse 90% 70% at 50% 45%, transparent 0%, rgba(0, 0, 0, 0.35) 100%);
  pointer-events: none;
}

/* —— 波纹 —— */
.pun-pass__waves {
  position: absolute;
  left: 50%;
  top: 44%;
  width: 120rpx;
  height: 120rpx;
  margin: -60rpx 0 0 -60rpx;
  pointer-events: none;
}

.pun-pass__wave {
  position: absolute;
  left: 0;
  top: 0;
  width: 120rpx;
  height: 120rpx;
  border: 3rpx solid rgba(255, 255, 255, 0.55);
  border-radius: 50%;
  animation: pun-pass-wave 1.4s ease-out infinite;
}

.pun-pass__wave--d1 {
  animation-delay: 0.35s;
}
.pun-pass__wave--d2 {
  animation-delay: 0.7s;
}

@keyframes pun-pass-wave {
  0% {
    transform: scale(0.4);
    opacity: 0.95;
  }
  100% {
    transform: scale(14);
    opacity: 0;
  }
}

/* —— 放射线（绕中心旋转） —— */
.pun-pass__rays {
  position: absolute;
  left: 50%;
  top: 44%;
  width: 0;
  height: 0;
}

.pun-pass__ray {
  position: absolute;
  left: 0;
  top: 0;
  width: 3rpx;
  height: 100rpx;
  margin-left: -1.5rpx;
  margin-top: -100rpx;
  background: linear-gradient(
    to top,
    rgba(255, 255, 255, 0),
    rgba(255, 255, 255, 0.88) 42%,
    rgba(255, 255, 255, 0)
  );
  transform-origin: 50% 100%;
  animation: pun-pass-ray-pulse 0.9s ease-out infinite;
}

@keyframes pun-pass-ray-pulse {
  0%,
  100% {
    opacity: 0.35;
  }
  50% {
    opacity: 1;
  }
}

/* —— 彩屑 —— */
.pun-pass__confetti {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.pun-pass__confetti-piece {
  position: absolute;
  top: -24rpx;
  width: 14rpx;
  height: 28rpx;
  border-radius: 4rpx;
  opacity: 0.95;
  animation: pun-pass-confetti-fall 2.4s linear infinite;
}

@keyframes pun-pass-confetti-fall {
  0% {
    transform: translate3d(0, 0, 0) rotate(0deg);
    opacity: 1;
  }
  100% {
    transform: translate3d(20rpx, 120vh, 0) rotate(720deg);
    opacity: 0.3;
  }
}

/* —— 角标 emoji —— */
.pun-pass__stars {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.pun-pass__star {
  position: absolute;
  font-size: 56rpx;
  opacity: 0;
  animation: pun-pass-star-pop 2.4s ease-in-out infinite;
  filter: drop-shadow(0 8rpx 16rpx rgba(0, 0, 0, 0.2));
}

.pun-pass__star--a {
  top: 18%;
  left: 12%;
  animation-delay: 0.1s;
}
.pun-pass__star--b {
  top: 22%;
  right: 14%;
  animation-delay: 0.35s;
  font-size: 48rpx;
}
.pun-pass__star--c {
  bottom: 28%;
  left: 18%;
  animation-delay: 0.55s;
  font-size: 64rpx;
}
.pun-pass__star--d {
  bottom: 24%;
  right: 12%;
  animation-delay: 0.75s;
}

@keyframes pun-pass-star-pop {
  0%,
  100% {
    opacity: 0;
    transform: scale(0.6) translateY(20rpx);
  }
  20%,
  80% {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

/* —— 主文案 —— */
.pun-pass__hero {
  position: absolute;
  left: 0;
  right: 0;
  top: 50%;
  transform: translateY(-52%);
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0 48rpx;
  text-align: center;
}

.pun-pass__kicker {
  font-size: 36rpx;
  font-weight: 800;
  letter-spacing: 0.2em;
  color: rgba(255, 255, 255, 0.95);
  text-shadow: 0 4rpx 24rpx rgba(0, 0, 0, 0.25);
  margin-bottom: 16rpx;
  animation: pun-pass-hero-line 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.pun-pass__headline {
  font-size: 72rpx;
  font-weight: 900;
  line-height: 1.15;
  color: #fff;
  text-shadow:
    0 8rpx 32rpx rgba(0, 0, 0, 0.28),
    0 0 40rpx rgba(255, 255, 255, 0.35);
  margin-bottom: 20rpx;
  animation: pun-pass-hero-line 0.6s cubic-bezier(0.22, 1, 0.36, 1) 0.08s both;
}

.pun-pass__sub {
  font-size: 30rpx;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.92);
  letter-spacing: 0.08em;
  text-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.2);
  animation: pun-pass-hero-line 0.65s cubic-bezier(0.22, 1, 0.36, 1) 0.15s both;
}

.pun-pass__kicker--battle {
  font-size: 40rpx;
  letter-spacing: 0.12em;
}

.pun-pass__headline--battle {
  font-size: 56rpx;
}

.pun-pass__sub--battle {
  font-size: 28rpx;
  opacity: 0.95;
}

.pun-pass__action {
  margin-top: 32rpx;
  min-width: 240rpx;
  height: 76rpx;
  border-radius: 999rpx;
  border: none;
  background: rgba(255, 255, 255, 0.96);
  color: #2e7d32;
  font-size: 30rpx;
  font-weight: 800;
  letter-spacing: 0.08em;
  box-shadow: 0 10rpx 24rpx rgba(0, 0, 0, 0.2);
}

.pun-pass--plain .pun-pass__action {
  color: #be185d;
}

.pun-pass--battle .pun-pass__action {
  color: #1d4ed8;
}

@keyframes pun-pass-hero-line {
  from {
    opacity: 0;
    transform: translateY(36rpx) scale(0.92);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
</style>
