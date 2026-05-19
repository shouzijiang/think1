<template>
  <view class="pun-wm">
    <view class="pun-wm-inner">
      <view v-for="row in ROWS" :key="row" class="pun-wm-row">
        <text v-for="col in COLS" :key="`${row}-${col}`" class="pun-wm-text">谐音梗猜一猜</text>
      </view>
    </view>
  </view>
</template>

<script setup>
/** 平铺斜向水印，pointer-events: none 不挡点击 */
const ROWS = 5
const COLS = 2
</script>

<style lang="scss" scoped>
.pun-wm {
  position: absolute;
  inset: 0;
  z-index: 1;
  pointer-events: none;
  overflow: hidden;

  .pun-wm-inner {
    position: absolute;
    width: 150%;
    height: 150%;
    left: -25%;
    top: -25%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: stretch;
    padding: 20rpx 0 100rpx;
    box-sizing: border-box;
    transform: rotate(-16deg);
    transform-origin: center center;
  }

  .pun-wm-row {
    display: flex;
    flex-direction: row;
    justify-content: space-around;
    align-items: center;
    width: 100%;
    flex-shrink: 0;
  }

  .pun-wm-text {
    font-size: 30rpx;
    line-height: 1.3;
    color: rgba(255, 255, 255, 0.1);
    text-shadow: 0 0 1rpx rgba(0, 0, 0, 0.2);
    white-space: nowrap;
  }
}
</style>
