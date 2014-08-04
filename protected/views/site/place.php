<?php
$id = $_REQUEST["id"];
$place = new PlacesObj($id);

if(!$place->loaded) :
?>
<h1 class="hide">Place Not Found</h1>
<div style="width:500px;margin-left:30%;margin-top:150px;">
    <img src="<?php echo StdLib::load_image_source("nolocation"); ?>" width="128px" alt="Place not found"/>
    <div style="width:330px;display:inline-block;vertical-align: top;padding-left:15px;">
        <div class="big">Could not find place</div>
        <p>This place has not been added yet. <a href="<?php echo Yii::app()->baseUrl; ?>">Go Back</a></p>
    </div>
</div>
<?php 
else:

# Session will keep which year/term (yt) user is looking at across the application
$yt = "20147";
if(!isset($_SESSION)) {
    session_start();
}
if(isset($_SESSION["yt"]) and strlen($_SESSION["yt"]) == 5) {
    $yt = $_SESSION["yt"];
}
if(isset($_REQUEST["yt"]) and strlen($_REQUEST["yt"]) == 5) {
    $yt = $_REQUEST["yt"];
    $_SESSION["yt"] = $yt;
}

# Load the metadata to display in the "Relevant Information" tab
$place->load_metadata();

# Load classes for a building
if($place->placetype->machinecode == "building") {
    $classes = StdLib::external_call(
        "http://assettdev.colorado.edu/ascore/api/buildingclasses",
        array(
            "building"  => $place->metadata->data["building_code"]["value"],
            "term"      => $yt, # Semester/Year to lookup
        )
    );
}
# Load classes for a classroom
else if($place->placetype->machinecode == "classroom") {
    $parent = $place->get_parent();
    $parent->load_metadata();
    $building_code = $parent->metadata->data["building_code"]["value"];
    $classes = StdLib::external_call(
        "http://assettdev.colorado.edu/ascore/api/classroomclasses",
        array(
            "building"  => $building_code,
            "classroom" => $place->placename,
            "term"      => $yt, # Semester/Year to lookup
        )
    );
}
# Don't load classes if other
else {
    $classes = array();
}

$yearterms = StdLib::external_call(
    "http://assettdev.colorado.edu/ascore/api/uniqueyearterms"
);

# Load children places
$childplaces = load_child_places($place->placeid);

# Get an array of child names
$childplace_names = array();
foreach($childplaces as $childplace) {
    $childplace_names[] = $childplace->placename;
}
?>

<h1 class="hide"><?php echo $place->placename; ?></h1>
<div class="entry">
    
    <ul id="breadcrumb" class="sticky" sticky="150">
      <li><a href="<?php echo Yii::app()->createUrl('index'); ?>"><span class="icon icon-home"> </span> Home</a></li>
      <?php if($place->placetype->machinecode == "classroom"): ?>
      <li>
          <a href="<?php echo Yii::app()->createUrl('place'); ?>?id=<?php echo $place->get_parent()->placename; ?>">
            <span class="icon icon-office"> </span> 
            <?php echo $place->get_parent()->placename; ?>
          </a>
      </li>
      <li><a href="#" onclick="javascript:return false;"><span class="icon icon-books"> </span> <?php echo $place->placename; ?></a></li>
      <?php elseif($place->placetype->machinecode == "lab"): ?>
      <li>
          <a href="<?php echo Yii::app()->createUrl('place'); ?>?id=<?php echo $place->get_parent()->placename; ?>">
            <span class="icon icon-office"> </span> 
            <?php echo $place->get_parent()->placename; ?>
          </a>
      </li>
      <li><a href="#" onclick="javascript:return false;"><span class="icon icon-lab"> </span> <?php echo $place->placename; ?></a></li>
      <?php else: ?>
      <li><a href="#" onclick="javascript:return false;"><span class="icon icon-office"> </span> <?php echo $place->placename; ?></a></li>
      <?php endif; ?>
    </ul>
    
    <h2><?php echo $place->placename; ?></h2>
    <div class="nav sticky" sticky="150">
        <ul>
            <li><div id="menu-placename"><?php echo $place->placename; ?></div></li>
            <li><a href="#home" onclick="javascript:return false;">Images <span class="icon icon-image2"> </span></a></li>
            <li><a href="#relevant-info" onclick="javascript:return false;"><?php echo $place->placetype->singular; ?> Info <span class="icon icon-office"> </span></a></li>
            <?php if($place->placetype->machinecode == "building"): ?>
            <li><a href="#roomuniquename" onclick="javascript:return false;">Rooms <span class="icon icon-enter"> </span></a></li>
            <li><a href="#googlemap" onclick="javascript:return false;">Google Map <span class="icon icon-map"> </span></a></li>
            <?php endif; ?>
            <li><a href="#buildingclasses" onclick="javascript:return false;">Classes <span class="icon icon-list"> </span></a></li>
        </ul>
    </div>
    <a name="home"></a>
    <div class="content">
        <div class="images" style="position:relative;">
            <img src="<?php echo WEB_LIBRARY_PATH; ?>images/loading-images.gif" class="loading-gif"/>
            <div class="galleria">
                <?php 
                if($place->has_pictures()) {
                    $pictures = $place->load_images();
                    foreach($pictures as $picture) {
                        if(!$picture->loaded) continue;
                        echo "<a href='".$picture->load_image_href()."'>";
                        $picture->render_thumb();
                        echo "</a>";
                    }
                } 
                else {
                    $picture = new PictureObj(1);
                    $picture->render();
                }
                ?>
            </div>
            <br class="clear" />
        </div>
        
        <br class="clear" />
        
        <a name="relevant-info"></a>
        <h3 name="relevant-info-header"><?php echo $place->placetype->singular; ?> Information</h3>
        <div class="meta">
            <div class="metachoice">
                <a href="#" class="ri selected" onclick="javascript:return false;">All</a> |
                <a href="#" class="ri" onclick="javascript:return false;">Students</a> |
                <a href="#" class="ri" onclick="javascript:return false;">Teachers</a>
            </div>
            <ul id="ri-list">
                <?php foreach($place->metadata->data as $index=>$data): ?>
                <?php if(($data["metatype"]!="students" and $data["metatype"]!="teachers" and $data["metatype"]!="both") or $data["value"] == "") continue; ?>
                <li class="<?=$data["metatype"];?>">
                    <div class="label"><?php if(isset($data["icon"])) { ?><span class="icon <?php echo $data["icon"]; ?>"> </span><?php } echo $data["display_name"]; ?></div>
                    <div class="value" style="word-wrap:break-word;"><?php echo $data["value"]; ?></div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <br class="clear" />
        <br class="clear" />
        <br class="clear" />
        <?php if($place->placetype->machinecode == "building"): ?>
        <a name="roomuniquename"></a>
        <h3 name="roomuniquename-header">Rooms in this <?php echo $place->placetype->singular; ?></h2>
        
        <ul class="rig columns-4">
        <?php if(!empty($childplaces)):  ?>
            <?php foreach($childplaces as $childplace):
                      $childplace_names[] = $childplace->placename;
                      $image = $childplace->load_first_image();
                      if(!$image->loaded) {
                        $image = new PictureObj(1);
                      }
                      if(!$image->loaded) {
                          continue;
                      }
                      $thumb = $image->get_thumb();
            ?>
            <li>
                <a href="<?php echo Yii::app()->createUrl('place'); ?>?id=<?php echo $childplace->placename; ?>">
                    <div class="image-container">
                        <img src="<?php echo $thumb; ?>" width="100%" height="100%" alt="<?php echo $childplace->placename; ?>" />
                    </div>
                    <div class="title"><?php echo $childplace->placename; ?></div>
                    <div class="placetype-<?php echo $childplace->placetype->machinecode; ?>"><?php echo $childplace->placetype->singular; ?></div>
                    <?php if(isset($childplace->description) and !empty($childplace->description)): ?>
                    <p><?php echo $childplace->description; ?></p>
                    <?php endif; ?>
                 </a>
            </li>
            <?php endforeach; ?>
        <?php endif; ?>
        </ul>

        <a name="googlemap"></a>
        <h3 name="googlemap-header">Google Map</h2>
        
        <div class="calign">
            <?php echo $place->metadata->data["googlemap"]["value"]; ?>
        </div>
        
        <br class="clear" />
        <?php endif; ?>
        
        <a name="yt"></a>
        <a name="buildingclasses"></a>
        <h3 name="buildingclasses-header">Classes in this <?php echo $place->placetype->singular; ?></h2>
        <div id="classes-container" class="right">
            Classes for 
            <label for="yt-select" class="hide">Select Year/Term</label>
            <select name="yt" id="yt-select">
                <?php foreach($yearterms as $yearterm): ?>
                <option value="<?php echo $yearterm["value"]; ?>" <?php if((isset($_REQUEST["yt"]) and $_REQUEST["yt"] == $yearterm["value"]) or (!isset($_REQUEST["yt"]) and isset($_SESSION["yt"]) and $_SESSION["yt"] == $yearterm["value"])) : ?>selected='selected'<?php endif; ?>><?php echo $yearterm["display"]; ?></option>
                <?php endforeach; ?>
            </select>
            <script>
            jQuery(document).ready(function($){
              $("#yt-select").change(function(){
                 window.location = "<?php echo Yii::app()->createUrl('place'); ?>?id=<?php echo $place->placename; ?>&yt="+$("#yt-select").val()+"#yt";
              });
            });
            </script>
        </div>
        <div class="hint" style="font-size:0.8em;margin:-10px 0px 10px 0px;">Note: Arts &amp; Sciences classes only.</div>
        
        <table class="fancy-table classes-table">
            <caption class="hide">Table of Arts &amp; Science Classes in this <?php echo $place->placetype->singular; ?>.</caption>
            <thead>
                <tr>
                    <th width="82px">Course</th>
                    <th class="calign">Section</th>
                    <th width="30%">Class</th>
                    <th width="86px" class="calign">Room</th>
                    <th class="calign">Term</th>
                    <th class="calign">Days</th>
                    <th class="calign">Times</th>
                </tr>
            </thead>
            <?php $count=0; foreach($classes as $class): $count++; ?>
                <?php
                # Do some processing before displaying
                $starttime  = $class["timestart"];
                $endtime    = $class["timeend"];
                $datetime = new DateTime($starttime);
                $starttime = $datetime->format("g:i a");
                $datetime = new DateTime($endtime);
                $endtime = $datetime->format("g:i a");
                
                $catalog_term = "2013-14";
                ?>
            <tr class="<?php echo ($count%2==0) ? 'odd' : 'even'; ?>">
                <td>
                    <a href="http://www.colorado.edu/catalog/<?php echo $catalog_term; ?>/courses?subject=<?php echo $class["subject"]; ?>&number=<?php echo $class["course"]; ?>" target="_blank">
                        <?php echo $class["subject"]; ?> <?php echo $class["course"]; ?>
                    </a>
                </td>
                <td class="calign"><?php echo substr("00".$class["section"],-3,3); ?></td>
                <td><?php echo $class["title"]; ?></td>
                <?php if($place->placetype->machinecode == "building" and in_array($class["building"]." ".$class["roomnum"],$childplace_names,FALSE)): ?>
                <td class="calign">
                    <a href="<?php echo Yii::app()->createUrl("place"); ?>?id=<?php echo $class["building"]." ".$class["roomnum"]; ?>"><?php echo $class["building"]." ".$class["roomnum"]; ?></a>
                </td>
                <?php else: ?>
                <td class="calign"><?php echo $class["building"]." ".$class["roomnum"]; ?></td>
                <?php endif; ?>
                <td class="calign"><?php 
                    $term = substr($class["yearterm"],-1,1);
                    switch($term) {
                        case 7: $term = "Fall"; break;
                        case 1: $term = "Spring"; break;
                        case 4: $term = "Summer"; break;
                        default: $term = $term; break;
                    }
                    echo $term." ".substr($class["yearterm"],0,4);
                ?></td>
                <td class="calign"><?php echo $class["meetingdays"]; ?></td>
                <td class="calign"><?php echo @$starttime." - ".@$endtime; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<script src="<?php echo WEB_LIBRARY_PATH; ?>jquery/modules/galleria/galleria-1.3.6.min.js"></script>


<script>

function scrollToHash()
{
    $('html,body').animate({
        scrollTop: $('[name='+window.location.hash.slice(1)+'-header]').offset().top
    }, 800);
}
$(function() {
  $('a[href*=#]:not([href=#])').click(function() {
    if (location.pathname.replace(/^\//,'') == this.pathname.replace(/^\//,'') && location.hostname == this.hostname) {
      var target = $(this.hash);
      if(this.hash == "#home") {
        $('html,body').animate({
          scrollTop: Math.floor(0)
        }, 800);
        return false;
      }
      var fhdr_height = 150;
      if($(".stuck").length != 0) {
          fhdr_height = ($("#breadcrumb").length > 0) ? $("#breadcrumb").outerHeight()+10 : 0;
      }
      target = target.length ? target : $('[name=' + this.hash.slice(1) +'-header]');
      if (target.length) {
        $('html,body').animate({
          scrollTop: Math.floor(target.offset().top - fhdr_height)
        }, 800);
        return false;
      }
    }
  });
});

// Display place name if we have scrolled past
function afterWindowScroll($stuck) {
    if($stuck == 1) {
        $("#menu-placename").show();
    }
    else {
        $("#menu-placename").hide();
    }
}
jQuery(document).ready(function($){
    init();
    
    $("table.classes-table").dataTable({
        "language": {
            "search": "Filter Classes:",
            "zeroRecords": "Could not find classes matching this filter.",
            "lengthMenu": "Show _MENU_ classes at a time",
            "emptyTable": "There are no classes for <?php echo yearterm_code_to_display($yt); ?> for the <?php echo $place->placename." ".strtolower($place->placetype->singular); ?>.",
            "info": "Showing <Strong>_START_</strong> to <Strong>_END_</strong> of <Strong>_TOTAL_</strong> classes",
        }
    });
    
    $("a.ri").click(function(){
       var $val = $(this).text();
       $("a.ri").removeClass("selected");
       $(this).addClass("selected");
       if($val == "All") {
           $("ul#ri-list li:hidden").each(function(){
              $(this).stop().show('fade'); 
           });
       } 
       else if($val=="Students") {
           $("ul#ri-list li.students:hidden").stop().show('fade');
           $("ul#ri-list li.none:visible").stop().hide('fade');
           $("ul#ri-list li.teachers:visible").stop().hide('fade');
       }
       else if($val=="Teachers") {
           $("ul#ri-list li.teachers:hidden").stop().show('fade');
           $("ul#ri-list li.none:visible").stop().hide('fade');
           $("ul#ri-list li.students:visible").stop().hide('fade');
       }
    });
    
    
   $("ul.rig li").hover(
       function(){
           $(this).fadeTo("fast",1);
       },
       function(){
           $(this).fadeTo("fast",0.8);
       }
   );
});
function init()
{
    Galleria.loadTheme('<?php echo WEB_LIBRARY_PATH; ?>jquery/modules/galleria/themes/classic/galleria.classic.min.js');
    Galleria.run('.galleria');
    Galleria.configure({
        'imageCrop': 'landscape',
        'imagePosition': 'center center',
        'lightbox': true,
    });
    Galleria.on("loadfinish",function(e){
        $("div.galleria").css("visibility","visible");
    });
}
</script>
<?php
endif; # Check if place is loaded
?>