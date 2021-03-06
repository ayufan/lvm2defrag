<?php
require_once 'read_vsn1.php';

$data = parse_vsn1(file_get_contents('data.txt'));
#print_r($data);

$vg = Array();
$pv = Array();

foreach($data as $vgname => $value)
  if(is_array($value) && isset($value['seqno']))
  {
    $vgtmp = Array('pv' => Array(), 'lv' => Array());
    $vg[$vgname] = &$vgtmp;
    
    foreach($value['physical_volumes'] as $pvname => $pvdata)
    {
      $pvtmp = Array('device' => $pvdata['device'],
                     'npe'    => $pvdata['pe_count'],
                     'uses'   => Array());
      $vgtmp['pv'][$pvname] = &$pvtmp;
      $pv[$pvdata['device']] = &$pvtmp;
      
      unset($pvtmp);
    }
    foreach($value['logical_volumes'] as $lvname => $lvdata)
    {
      #$lvtmp = Array('name' => $lvname, 'parts' => Array());
      #$vgtmp['lv'][$lvname] = &$lvtmp;
      foreach($lvdata as $datakey => $datavalues)
      {
        if(preg_match('/^segment[0-9]/', $datakey))
        {
          $seg = (int)substr($datakey, 7);
        
          $nparts = 1; $partkey = 'parts';
          $fpe    = $datavalues['start_extent'];
          $npe    = $datavalues['extent_count'];
          if($datavalues['type'] == 'mirror')
          {
            $nparts = $datavalues['mirror_count'];
            $partkey = 'mirrors';
          }
          else if($datavalues['type'] == 'striped')
          {
            $nparts = $datavalues['stripe_count'];
            $partkey = 'stripes';
            $npe /= $nparts;
          }
          else if($datavalues['type'] == 'raid1' || $datavalues['type'] == 'raid5' || $datavalues['type'] == 'thin-pool' || $datavalues['type'] == 'thin' || $datavalues['type'] == 'cache-pool')
          {
            continue;
          }
          else
          {
            die("Unsupported ${datavalues['type']}\n");
          }

          for($n=0; $n < $nparts; ++$n)
          {
            $partcode = strtoupper(base_convert(10+$n,10,36));
            $lvnamepart = "{$lvname}$partcode-$seg";
            $lvpart = &$vgtmp['lv'][$lvnamepart];
            
            $pvname = $datavalues[$partkey][$n*2+0];
            $pvoffs = $datavalues[$partkey][$n*2+1];
            
            $lvpart =
              Array(//'start'  => $fpe,
                    'count'  => $npe,
                    'pv'     => $pvname,
                    'pvoffs' => $pvoffs);
           
            if($datavalues['type'] != "mirrors")
            {
              $vgtmp['pv'][$pvname]['uses'][$pvoffs] =
                Array('count'  => $npe,
                      'lv'     => $lvnamepart,
                    // 'lvoffs' => $fpe
                    );
            }
            unset($lvpart);
          }
        }
      }
      #unset($lvtmp);
    }
    
    unset($vgtmp);
  }

foreach($pv as &$pvdata)
  ksort($pvdata['uses']);
unset($pvdata);

#print_r($vg);

foreach($vg as $vgname => $vgdata)
{
  print "!! $vgname\n\n";
  foreach($vgdata['pv'] as $pvname => $pvdata)
  {
    print "! {$pvdata['device']}\n";
    $begin = 0;
    $end   = $pvdata['npe'];
    foreach($pvdata['uses'] as $offset => $data)
    {
      if($offset > $begin)
        printf("(%d)\t%s\n", $offset-$begin, '-');
      printf("%d\t%s\n", $data['count'], $data['lv']);
      $begin = $offset + $data['count'];
    }
    if($end > $begin)
      printf("(%d)\t%s\n", $end-$begin, '-');
    print "\n";
  }
}
