<?php
require_once 'COMMON/afs_tools.php';

/** @brief Sort mode of the facets. */
abstract class AfsFacetSort extends BasicEnum
{
    /** @brief Strict mode.
     *
     * All facets are sorted according to provided sort order list. Facets not
     * present in the list are removed from reply. */
    const STRICT = 'STRICT';
    /** @brief Smooth mode.
     *
     * Facets are sorted at AfsReplysetHelper level. This allows to retrieve all
     * facets. First facets are sorted according to provided sort order list,
     * other ones follow as they appear in AFS search engine reply. */
    const SMOOTH = 'SMOOTH';
}


