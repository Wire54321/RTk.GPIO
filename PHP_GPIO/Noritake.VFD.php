<?php
/*------------------------------------------------------------------------------
  Noritake.VFD
  PHP drivers for the Noritake GU256x64D-7000 VFD
--------------------------------------------------------------------------------
  Daniel Bull
  daniel@neonhorizon.co.uk
------------------------------------------------------------------------------*/
namespace PHP_GPIO\Noritake;


class VFD
{
  // Pins
  const PINS = array(
    'Busy' => 'the Busy Signal (VFD pin 3)',
    'Write' => 'the Write Signal (VFD pin 5)',
    'D0' => 'Data Bus 0 (VFD pin 7)',
    'D1' => 'Data Bus 1 (VFD pin 8)',
    'D2' => 'Data Bus 2 (VFD pin 9)',
    'D3' => 'Data Bus 3 (VFD pin 10)',
    'D4' => 'Data Bus 4 (VFD pin 11)',
    'D5' => 'Data Bus 5 (VFD pin 12)',
    'D6' => 'Data Bus 6 (VFD pin 13)',
    'D7' => 'Data Bus 7 (VFD pin 14)',
  );

  // Settings
  private $gpio = NULL;
  public  $pins = array();


  /*------------------------------------------------------------------------------
    Constructor
  ------------------------------------------------------------------------------*/
  public function __construct($gpio = NULL)
  {
    // Sanity
    if(get_class($gpio) !== 'PHP_GPIO\RTk\GPIO')
      trigger_error('A GPIO connection must be supplied when setting up a VFD', E_USER_ERROR);

    $this->gpio = $gpio;
  }


  /*----------------------------------------------------------------------------
    Prepare for use
  ----------------------------------------------------------------------------*/
  public function initialise()
  {
    // Sanity
    foreach(self::PINS as $name => $description)
      if(!isset($this->pins[$name])) // Full validation done by RTk/GPIO library
        trigger_error('pins['.$name.'] is not set, which GPIO pin is '.$description.' connected to?', E_USER_ERROR);
    foreach($this->pins as $name => $pin)
      if(!array_key_exists($name, self::PINS))
        trigger_error('Unknown pin '.$name.' configured?', E_USER_ERROR);

    // Set up the pins
    foreach($this->pins as $name => $pin)
      if($name == 'Busy')
        $this->gpio->setup($pin, $this->gpio::IN, array('pull_up_down' => $this->gpio::PUD_DOWN));
      elseif($name == 'Write')
        $this->gpio->setup($pin, $this->gpio::OUT, array('initial' => $this->gpio::HIGH));
      else
        $this->gpio->setup($pin, $this->gpio::OUT, array('initial' => $this->gpio::LOW));

    // Reset
    $this->reset();
  }


  /*----------------------------------------------------------------------------
    Reset
  ----------------------------------------------------------------------------*/
  public function reset()
  {
    $this->command(0x1b, 0x40);
  }


  /*----------------------------------------------------------------------------
    Power
  ----------------------------------------------------------------------------*/
  public function power($value = TRUE)
  {
    if(!is_bool($value))
      trigger_error('Invalid setting, use power(TRUE) or power(FALSE)', E_USER_ERROR);
    $this->command(0x1f, 0x28, 0x61, 0x40, $value + 0);
  }


  /*----------------------------------------------------------------------------
    Adjust the brightness
  ----------------------------------------------------------------------------*/
  public function brightness($value = 8)
  {
    if(!is_numeric($value) || $value < 0 || $value > 8)
      trigger_error('Invalid brightness, value should be between 0 (off) and 8 (max)', E_USER_ERROR);
    $this->command(0x1f, 0x58, $value);
  }


  /*----------------------------------------------------------------------------
    Screensaver
  ----------------------------------------------------------------------------*/
  public function screensaver($value = FALSE)
  {
    if(!is_bool($value))
      trigger_error('Invalid setting, use screensaver(TRUE) or screensaver(FALSE)', E_USER_ERROR);
    $this->command(0x1f, 0x28, 0x61, 0x40, $value ? 4 : 2);
  }


  /*----------------------------------------------------------------------------
    Output a string
  ----------------------------------------------------------------------------*/
  public function output($string)
  {
    // Convert carriage returns to DOS format for VFD
    $string = strtr(strtr($string, array("\n" => "\n\r")), array("\r\r" => "\r"));

    // Send string
    for($i = 0; $i < strlen($string); $i++)
      $this->send_byte(ord($string[$i]));
  }


  /*----------------------------------------------------------------------------
    Clear
  ----------------------------------------------------------------------------*/
  public function clear()
  {
    $this->command(0x0c);
  }


  /*----------------------------------------------------------------------------
    Home
  ----------------------------------------------------------------------------*/
  public function home()
  {
    $this->command(0x0b);
  }


  /*----------------------------------------------------------------------------
    Font
  ----------------------------------------------------------------------------*/
  public function font($wide = TRUE, $proportional = FALSE, $scale_x = 1, $scale_y = 1)
  {
    if(!is_bool($wide))
      trigger_error('Invalid width, use font(width, proportional, scale_x, scale_y) where width is TRUE or FALSE', E_USER_ERROR);
    if(!is_bool($proportional))
      trigger_error('Invalid proportional, use font(width, proportional, scale_x, scale_y) where proportional is TRUE or FALSE', E_USER_ERROR);
    if(!is_numeric($scale_x) || $scale_x < 1 || $scale_x > 4)
      trigger_error('Invalid scale_x, use font(width, proportional, scale_x, scale_y) where scale_x is 1 to 4', E_USER_ERROR);
    if(!is_numeric($scale_y) || $scale_y < 1 || $scale_y > 2)
      trigger_error('Invalid scale_x, use font(width, proportional, scale_x, scale_y) where scale_y is 1 to 2', E_USER_ERROR);

    $this->command(0x1f, 0x28, 0x67, 0x03, $wide + $proportional * 2);
    $this->command(0x1f, 0x28, 0x67, 0x40, $scale_x, $scale_y);
  }


  /*----------------------------------------------------------------------------
    Inverse video
  ----------------------------------------------------------------------------*/
  public function inverse($value = TRUE)
  {
    if(!is_bool($value))
      trigger_error('Invalid setting, use inverse(TRUE) or inverse(FALSE)', E_USER_ERROR);
    $this->command(0x1f, 0x72, $value + 0);
  }


  /*----------------------------------------------------------------------------
    Video overlay mode
  ----------------------------------------------------------------------------*/

  const OVERLAY_NONE = 0;
  const OVERLAY_OR   = 1;
  const OVERLAY_AND  = 2;
  const OVERLAY_XOR  = 3;

  public function overlay($value = OVERLAY_NONE)
  {
    if(!in_array($value, array(self::OVERLAY_NONE, self::OVERLAY_OR, self::OVERLAY_AND, self::OVERLAY_XOR), TRUE))
      trigger_error('Invalid overlay, use VFD::OVERLAY_NONE, VFD::OVERLAY_OR, VFD::OVERLAY_AND or VFD::OVERLAY_XOR', E_USER_ERROR);
    $this->command(0x1f, 0x77, $value);
  }


  /*----------------------------------------------------------------------------
    Wrapping
  ----------------------------------------------------------------------------*/
  public function wrap($value = FALSE)
  {
    if(!is_bool($value))
      trigger_error('Invalid setting, use wrap(TRUE) or wrap(FALSE)', E_USER_ERROR);
    $this->command(0x1f, 0x28, 0x77, 0x10, !$value + 0);
  }


  /*----------------------------------------------------------------------------
    Automatic Scrolling
  ----------------------------------------------------------------------------*/
  const AUTO_SCROLL_WRAP       = 1;
  const AUTO_SCROLL_VERTICAL   = 2;
  const AUTO_SCROLL_HORIZONTAL = 3;

  public function auto_scroll($mode = AUTO_SCROLL_WRAP, $speed = '')
  {
    if(!in_array($mode, array(self::AUTO_SCROLL_WRAP, self::AUTO_SCROLL_VERTICAL, self::AUTO_SCROLL_HORIZONTAL), TRUE))
      trigger_error('Invalid mode, use auto_scroll(VFD::AUTO_SCROLL_WRAP), auto_scroll(VFD::AUTO_SCROLL_VERTICAL) or auto_scroll(VFD::AUTO_SCROLL_HORIZONTAL, speed)', E_USER_ERROR);

    $this->command(0x1f, $mode);

    if($speed !== '' && $mode === self::AUTO_SCROLL_HORIZONTAL)
    {
      if(!is_numeric($speed) || $speed < 0 || $speed > 31)
        trigger_error('Invalid scroll speed, value should be between 0 (fast) and 31 (slow)', E_USER_ERROR);
      $this->command(0x1f, 0x73, $speed);
    }
    elseif($speed !== '')
      trigger_error('Scroll speed can only be set for AUTO_SCROLL_HORIZONTAL mode', E_USER_ERROR);
  }


  /*----------------------------------------------------------------------------
    Scroll (amount is y, to scroll x multiply by number of rows, Eg 8)
  ----------------------------------------------------------------------------*/
  public function scroll($amount, $repeat, $delay)
  {
    if(!is_numeric($amount) || $amount < 0 || $amount > 4095)
      trigger_error('Invalid amount, use auto_scroll(amount, repeat, delay) where amount is between 0 and 4095', E_USER_ERROR);
    if(!is_numeric($repeat) || $repeat < 0 || $repeat > 65535)
      trigger_error('Invalid repeat, use auto_scroll(amount, repeat, delay) where repeat is between 0 and 65535', E_USER_ERROR);
    if(!is_numeric($delay) || $delay < 0 || $delay > 255)
      trigger_error('Invalid delay, use auto_scroll(amount, repeat, delay) where delay is between 0 and 255', E_USER_ERROR);

    $this->command(0x1f, 0x28, 0x61, 0x10, $amount % 256, floor($amount / 256), $repeat % 256, floor($repeat / 256), $delay);
  }


  /*----------------------------------------------------------------------------
    Show cursor
  ----------------------------------------------------------------------------*/
  public function cursor_show($value = TRUE)
  {
    if(!is_bool($value))
      trigger_error('Invalid setting, use cursor_show(TRUE) or cursor_show(FALSE)', E_USER_ERROR);

    $this->command(0x1f, 0x43, $value + 0);
  }


  /*----------------------------------------------------------------------------
    Position cursor
  ----------------------------------------------------------------------------*/
  public function cursor_position($x, $y)
  {
    if(!is_numeric($x) || $x < 0 || $x > 511)
      trigger_error('Invalid x, use cursor_position(x, y) where x is between 0 and 511', E_USER_ERROR);
    if(!is_numeric($y) || $y < 0 || $y > 7)
      trigger_error('Invalid y, use cursor_position(x, y) where y is between 0 and 7', E_USER_ERROR);

    $this->command(0x1f, 0x24, $x % 256, floor($x / 256), $y % 256, floor($y / 256));
  }


  /*----------------------------------------------------------------------------
    Move cursor left
  ----------------------------------------------------------------------------*/
  public function cursor_left()
  {
    $this->command(0x08);
  }


  /*----------------------------------------------------------------------------
    Move cursor right
  ----------------------------------------------------------------------------*/
  public function cursor_right()
  {
    $this->command(0x09);
  }


  /*----------------------------------------------------------------------------
    Select a previously defined window (0 is base window)
  ----------------------------------------------------------------------------*/
  public function window_select($value = 0)
  {
    if(!is_numeric($value) || $value < 0 || $value > 4)
      trigger_error('Invalid window, window should be between 0 and 4', E_USER_ERROR);

    $this->command(0x10 + $value);
  }


  /*----------------------------------------------------------------------------
    Create a window
  ----------------------------------------------------------------------------*/
  public function window_create($window, $x, $y, $width, $height)
  {
    if(!is_numeric($window) || $window < 1 || $window > 4)
      trigger_error('Invalid window, use window_create(window, x, y, width, height) where window is between 1 and 4', E_USER_ERROR);
    if(!is_numeric($x) || $x < 0 || $x > 511)
      trigger_error('Invalid x, use window_create(window, x, y, width, height) where x is between 0 and 511', E_USER_ERROR);
    if(!is_numeric($y) || $y < 0 || $y > 7)
      trigger_error('Invalid y, use window_create(window, x, y, width, height) where y is between 0 and 7', E_USER_ERROR);
    if(!is_numeric($width) || $width < 1 || $width > 512)
      trigger_error('Invalid width, use window_create(window, x, y, width, height) where width is between 1 and 512', E_USER_ERROR);
    if(!is_numeric($height) || $height < 1 || $height > 8)
      trigger_error('Invalid height, use window_create(window, x, y, width, height) where height is between 1 and 8', E_USER_ERROR);

    $this->command(0x1f, 0x28, 0x77, 0x02, $window, 0x01, $x % 256, floor($x / 256), $y % 256, floor($y / 256), $width % 256, floor($width / 256), $height % 256, floor($height / 256));
  }


  /*----------------------------------------------------------------------------
    Delete a window
  ----------------------------------------------------------------------------*/
  public function window_delete($value)
  {
    if(!is_numeric($value) || $value < 1 || $value > 4)
      trigger_error('Invalid window to delete, window should be between 1 and 4', E_USER_ERROR);

    $this->command(0x1f, 0x28, 0x77, 0x02, $value, 0x00);
  }


  /*----------------------------------------------------------------------------
    Import an XBM image from a file
  ----------------------------------------------------------------------------*/
  public function import_xbm($file)
  {
    // Sanity check
    if(!file_exists($file))
      trigger_error('XBM file '.$file.' does not exist', E_USER_ERROR);

    // Get the file contents
    if(($xbm = file_get_contents($file)) == FALSE)
      trigger_error('Cannot open file '.$file, E_USER_ERROR);

    // Sanity check
    $xbm = explode("\n", $xbm);
    if(count($xbm) < 4)
      trigger_error($file.' is not a valid XBM file', E_USER_ERROR);

    // Get width
    if(substr($xbm[0], 0, 15) != '#define _width ')
      trigger_error('Cannot find image width in XBM file '.$file, E_USER_ERROR);
    if(!is_numeric($width = substr($xbm[0], 15)) || $width < 1)
      trigger_error('Bad image width "'.$width.'" specified in '.$file, E_USER_ERROR);
    unset($xbm[0]);
    $width_blocks = ceil($width / 8);

    // Get height
    if(substr($xbm[1], 0, 16) != '#define _height ')
      trigger_error('Cannot find image height in XBM file '.$file, E_USER_ERROR);
    if(!is_numeric($height = substr($xbm[1], 16)) || $height < 1)
      trigger_error('Bad image height "'.$height.'" specified in '.$file, E_USER_ERROR);
    unset($xbm[1]);
    $height_blocks = ceil($height / 8);

    // Clean up data
    if($xbm[2] != 'static char _bits[] = {')
      trigger_error('Cannot find image data in XBM file '.$file, E_USER_ERROR);
    unset($xbm[2]);
    $xbm = trim(implode('', $xbm));
    if(substr($xbm, -3) != ' };')
      trigger_error('XBM file '.$file.' appears to be cropped?', E_USER_ERROR);
    $xbm = explode(',', substr($xbm, 0, -3));

    // Check its sensible
    if(count($xbm) != $width_blocks * $height)
      trigger_error('Image data length incorrect in XBM file '.$file, E_USER_ERROR);

    // Convert XBM data to VFD data
    // XBM is horizontal blocks of 8 pixels, left to right top to bottom
    // VFD is vertical blocks of 8 pixels LSB at the bottom, top to bottom, left to right
    for($x = 0; $x < $width; $x++)
      for($y = 0; $y < $height; $y++)
      {
        // Calulate positions
        $xbm_byte_position = floor($x / 8) + $y * $width_blocks;
        $xbm_byte_mask     = pow(2, $x % 8);
        $vfd_byte_position = floor($y / 8) + $x * $height_blocks;
        $vfd_byte_mask     = pow(2, 7 - $y % 8);

        // Initialise the VFD byte if it doesnt exist
        if(!isset($data[$vfd_byte_position])) $data[$vfd_byte_position] = 0;

        // Get and check the XBM byte
        $xbm_byte = hexdec(trim($xbm[$xbm_byte_position]));
        if($xbm_byte < 0b00000000 || $xbm_byte > 0b11111111)
          trigger_error('Incorrect image data value "'.$xbm[$xbm_byte_position].'" in XBM file '.$file, E_USER_ERROR);

        // If the bit is on add it to the VFD
        if(($xbm_byte & $xbm_byte_mask) > 0)
          $data[$vfd_byte_position] += $vfd_byte_mask;
      }

    return array('width' => $width, 'height' => $height, 'data' => $data);
  }


  /*----------------------------------------------------------------------------
    Display an image
  ----------------------------------------------------------------------------*/
  public function image($image_data)
  {
    if(!isset($image_data['width']) || !isset($image_data['height']) || !isset($image_data['data']))
      trigger_error('Invalid image data', E_USER_ERROR);

    if(!is_numeric($image_data['width']) || $image_data['width'] < 1 || $image_data['width'] > 512)
      trigger_error('Invalid image width, width must be between 1 and 512', E_USER_ERROR);
    if(!is_numeric($image_data['height']) || $image_data['height'] < 1 || $image_data['height'] > 64)
      trigger_error('Invalid image height, height must be between 1 and 64', E_USER_ERROR);

    // Check data
    if(count($image_data['data']) != ceil($image_data['height'] / 8) * $image_data['width'])
      trigger_error('Image data block doesn\'t match its size', E_USER_ERROR);

    // Header
    $this->command(0x1f, 0x28, 0x66, 0x11, $image_data['width'] % 256, floor($image_data['width'] / 256), ceil($image_data['height'] / 8), 0, 1);

    // Data
    foreach($image_data['data'] as $byte)
      $this->send_byte($byte, FALSE); // We send bytes without checking for the busy line as the VFD is faster than serial anyway
  }


  /*----------------------------------------------------------------------------
    Execute single or multiple commands
  ----------------------------------------------------------------------------*/
  public function command()
  {
    // Get the commands
    $commmands = func_get_args();

    // Sanity
    if(count($commmands) < 1) return;

    // Execute commands
    foreach($commmands as $command)
    {
      // Sanity
      if(!is_numeric($command) || $command < 0 || $command > 255)
      {
        var_dump($command);
        trigger_error('Invalid command()', E_USER_ERROR);
      }

      $this->send_byte($command);
    }
  }


  /*----------------------------------------------------------------------------
    Send a byte of data
  ----------------------------------------------------------------------------*/
  private function send_byte($value, $wait = TRUE)
  {
    // Set Byte
    $this->gpio->output($this->pins['D0'], ($value & 0b00000001) > 0 ? $this->gpio::HIGH : $this->gpio::LOW);
    $this->gpio->output($this->pins['D1'], ($value & 0b00000010) > 0 ? $this->gpio::HIGH : $this->gpio::LOW);
    $this->gpio->output($this->pins['D2'], ($value & 0b00000100) > 0 ? $this->gpio::HIGH : $this->gpio::LOW);
    $this->gpio->output($this->pins['D3'], ($value & 0b00001000) > 0 ? $this->gpio::HIGH : $this->gpio::LOW);
    $this->gpio->output($this->pins['D4'], ($value & 0b00010000) > 0 ? $this->gpio::HIGH : $this->gpio::LOW);
    $this->gpio->output($this->pins['D5'], ($value & 0b00100000) > 0 ? $this->gpio::HIGH : $this->gpio::LOW);
    $this->gpio->output($this->pins['D6'], ($value & 0b01000000) > 0 ? $this->gpio::HIGH : $this->gpio::LOW);
    $this->gpio->output($this->pins['D7'], ($value & 0b10000000) > 0 ? $this->gpio::HIGH : $this->gpio::LOW);

    // Strobe data
    $this->strobe($wait);

    return TRUE;
  }


  /*----------------------------------------------------------------------------
    Strobe the data through
  ----------------------------------------------------------------------------*/
  private function strobe($wait = TRUE)
  {
    // Wait until we are not busy
    if($wait)
      while($this->gpio->input($this->pins['Busy']))
      {}

    // Toggle the write line
    $this->gpio->output($this->pins['Write'], $this->gpio::LOW);
    $this->gpio->output($this->pins['Write'], $this->gpio::HIGH);
  }

}
