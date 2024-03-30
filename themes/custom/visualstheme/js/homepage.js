document.addEventListener("DOMContentLoaded", function () {
  let splide_news = new Splide('.splide.image-updates', {
    arrows: false,
    type: 'loop',
    perPage: 1,
    autoplay: true,
  });
  splide_news.mount();

  let splide_second_news = new Splide('.news-updates .splide', {
    arrows: false,
    type: 'loop',
    perPage: 3,
    pagination: true,
    autoplay: true,
    breakpoints: {
      1024: {
        perPage: 2,
      },
      640: {
        perPage: 1,
      },
    }
  });
  splide_second_news.mount();

  let nat_schedule = new Splide('.splide.nat-schedule', {
    arrows: true,
    type: 'fade',
    perPage: 1,
    pagination: false,
  });
  nat_schedule.mount();

  let senate_schedule = new Splide('.splide.senate-schedule', {
    arrows: true,
    type: 'fade',
    perPage: 1,
    pagination: false,
  });
  senate_schedule.mount();

  let nat_committee = new Splide('.nat-committee-work .splide', {
    arrows: false,
    type: 'loop',
    perPage: 3,
    pagination: true,
    autoplay: true,
    speed: 250,
    breakpoints: {
      1024: {
        perPage: 2,
      },
      640: {
        perPage: 1,
      },
    }
  });
  nat_committee.mount();

  let sen_committee = new Splide('.sen-committee-work .splide', {
    arrows: false,
    type: 'loop',
    perPage: 3,
    pagination: true,
    autoplay: true,
    breakpoints: {
      1024: {
        perPage: 2,
      },
      640: {
        perPage: 1,
      },
    }
  });
  sen_committee.mount();

});

function openRelease(event, releaseName) {
  var i, tabcontent, tablinks;
  tabcontent = document.getElementsByClassName("tabcontent");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }
  tablinks = document.getElementsByClassName("tablinks");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" active", "");
  }
  const element = document.getElementById(releaseName);
  element.style.display = "block";
  
  event.currentTarget.className += " active";
}

document.getElementById("defaultOpen").click();

function handleMouseMove(event) {
  var countryTitle = event.target.getAttribute("title");
  var tooltip = document.getElementById("tooltip");

  var x = event.clientX;
  var y = event.clientY;

  var svgElement = event.target.ownerSVGElement; // Get the parent SVG element
  var svgRect = svgElement.getBoundingClientRect();

  // Check if the mouse event is within the boundaries of the SVG element
  if (
    x >= svgRect.left &&
    x <= svgRect.right &&
    y >= svgRect.top &&
    y <= svgRect.bottom
  ) {
    tooltip.style.left = x + 10 + "px";
    tooltip.style.top = y - 20 + "px";
    tooltip.innerHTML = countryTitle;
    tooltip.classList.add("active");
  } else {
    // If the mouse is outside the SVG element, hide the tooltip
    tooltip.classList.remove("active");
  }
}

// Hide the tooltip when the mouse leaves the SVG element
var svgElement = document.querySelector("svg");
svgElement.addEventListener("mouseleave", function () {
  var tooltip = document.getElementById("tooltip");
  tooltip.classList.remove("active");
});


let notice_index = 0;

const notice_items = document.querySelectorAll('.notice-slider li');

setInterval(() => {
  notice_items[notice_index].classList.remove('ease-in');

  notice_index = (notice_index + 1) % notice_items.length;

  notice_items[notice_index].classList.add('ease-in');

}, 3000);
