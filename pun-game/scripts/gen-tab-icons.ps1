$code = @'
using System;
using System.Drawing;
using System.Drawing.Drawing2D;
public class TabIconGen {
  public static void Save(string path, int rr, int gg, int bb) {
    var bmp = new Bitmap(81, 81);
    using (var gfx = Graphics.FromImage(bmp)) {
      gfx.SmoothingMode = SmoothingMode.AntiAlias;
      gfx.Clear(Color.Transparent);
      using (var br = new SolidBrush(Color.FromArgb(255, rr, gg, bb)))
        gfx.FillEllipse(br, 10, 10, 61, 61);
    }
    bmp.Save(path, System.Drawing.Imaging.ImageFormat.Png);
  }
}
'@
Add-Type -TypeDefinition $code -ReferencedAssemblies System.Drawing
$dir = Join-Path $PSScriptRoot '..\src\static\tab'
New-Item -ItemType Directory -Force -Path $dir | Out-Null
[TabIconGen]::Save((Join-Path $dir 'game.png'),145,213,139)
[TabIconGen]::Save((Join-Path $dir 'game-active.png'),90,158,82)
[TabIconGen]::Save((Join-Path $dir 'rank.png'),142,173,207)
[TabIconGen]::Save((Join-Path $dir 'rank-active.png'),90,122,158)
[TabIconGen]::Save((Join-Path $dir 'levels.png'),232,184,109)
[TabIconGen]::Save((Join-Path $dir 'levels-active.png'),201,143,58)
[TabIconGen]::Save((Join-Path $dir 'mine.png'),201,168,232)
[TabIconGen]::Save((Join-Path $dir 'mine-active.png'),139,107,184)
Write-Output "ok $dir"
