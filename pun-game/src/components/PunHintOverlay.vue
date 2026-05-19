<template>
  <view
    v-if="show"
    class="pun-hint"
    @click="emit('close')"
  >
    <view class="pun-hint__backdrop" />
    <view class="pun-hint__card" @click.stop>
      <text class="pun-hint__kicker">答案提示</text>
      <text class="pun-hint__title">
        {{ isComplete ? '提示已全部解锁' : `提示进度 (${step}/${maxSteps})` }}
      </text>
      <view class="pun-hint__body-wrap">
        <text class="pun-hint__body">{{ hintText }}</text>
      </view>
      <button class="pun-hint__btn" @click="emit('close')">知道了</button>
    </view>
  </view>
</template>

<script setup>
defineProps({
  show: { type: Boolean, default: false },
  hintText: { type: String, default: '' },
  step: { type: Number, default: 0 },
  maxSteps: { type: Number, default: 0 },
  isComplete: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])
</script>

<style lang="scss" scoped>
.pun-hint {
  position: fixed;
  inset: 0;
  z-index: 998;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 44rpx;
  box-sizing: border-box;

  .pun-hint__backdrop {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 30% 20%, rgba(111, 184, 104, 0.2), transparent 50%),
      rgba(20, 36, 32, 0.45);
    backdrop-filter: blur(4px);
  }

  .pun-hint__card {
    position: relative;
    width: 100%;
    max-width: 620rpx;
    border-radius: 30rpx;
    padding: 34rpx 30rpx 28rpx;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(246, 253, 248, 0.98));
    border: 2rpx solid rgba(111, 184, 104, 0.35);
    box-shadow: 0 18rpx 48rpx rgba(18, 78, 43, 0.2);
  }

  .pun-hint__kicker {
    display: block;
    text-align: center;
    font-size: 24rpx;
    color: #73a37a;
    font-weight: 700;
    letter-spacing: 0.08em;
  }

  .pun-hint__title {
    display: block;
    text-align: center;
    margin-top: 10rpx;
    font-size: 34rpx;
    color: #2e7d32;
    font-weight: 900;
  }

  .pun-hint__body-wrap {
    margin-top: 20rpx;
    border-radius: 20rpx;
    background: rgba(231, 245, 234, 0.78);
    border: 2rpx solid rgba(111, 184, 104, 0.2);
    padding: 24rpx 22rpx;
  }

  .pun-hint__body {
    display: block;
    text-align: center;
    font-size: 34rpx;
    line-height: 1.6;
    color: #365d3a;
    font-weight: 700;
    word-break: break-all;
  }

  .pun-hint__btn {
    margin-top: 24rpx;
    width: 100%;
    height: 78rpx;
    border-radius: 999rpx;
    border: none;
    background: linear-gradient(180deg, #a8e6a2, #6fb868);
    color: #fff;
    font-size: 30rpx;
    font-weight: 800;

    &::after {
      border: none;
    }
  }
}
</style>
