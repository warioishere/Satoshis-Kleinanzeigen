document.addEventListener("DOMContentLoaded", function (event) {
  var ComboBlocksPostGrid = document.querySelectorAll(
    ".ComboBlocksFilterableGridNav"
  );


  if (ComboBlocksPostGrid != null) {
    ComboBlocksPostGrid.forEach((item) => {
      var postgridargs = item.getAttribute("data-postgridargs");
      var postgridargsObj = JSON.parse(postgridargs);
      var blockId = postgridargsObj.blockId;

      var activeFilter = postgridargsObj.activeFilter;
      var activeFilterSlug = activeFilter.slug;
      var perPage = postgridargsObj.perPage;
      var logicBetweenGroups = postgridargsObj.logicBetweenGroups;
      var logicWithinGroup = postgridargsObj.logicWithinGroup;
      var multifilter = postgridargsObj.multifilter;

      activeFilterSlug =
        activeFilterSlug.length == 0 ? "all" : activeFilterSlug;
      activeFilterSlug =
        activeFilterSlug == "all" ? "all" : "." + activeFilterSlug;

      var lazyLoad = postgridargsObj.lazyLoad;
      var lazyLoadEnable = lazyLoad.enable;

      if (lazyLoadEnable == "yes") {
        var lazyloadWrap = document.querySelector("#lazyload-" + blockId);

        item.style.display = "block";
        lazyloadWrap.style.display = "none";
      }

      var containerEl = document.querySelector(".items");
      var mixer = mixitup(containerEl, {
        selectors: {
          control: ".pg-filter-" + blockId,
          target: ".item",
          pageList: ".pager-list-" + blockId,
        },

        pagination: {
          limit: perPage,
          maxPagers: 5,
          hidePageListIfSinglePage: true,
        },
        templates: {
          pagerPrev:
            '<span class="item prev pg-filter-' +
            blockId +
            ' ${classNames}" data-page="prev">Prev</span>',
          pagerNext:
            '<span class="item next pg-filter-' +
            blockId +
            ' ${classNames}" data-page="next">Next</span>',
          pager:
            '<span class="item pg-filter-' +
            blockId +
            ' ${classNames}" data-page="${pageNumber}">${pageNumber}</span>',
        },

        multifilter: {
          enable: multifilter,
          logicWithinGroup: logicWithinGroup,
          logicBetweenGroups: logicBetweenGroups,
        },

        load: {
          filter: activeFilterSlug,
        },
      });
    });
  }

});
