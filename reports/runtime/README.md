Intermediate report artifacts live here.

Purpose:
- keep rerun and chunked source CSVs out of `/private/tmp`
- keep transient-but-important source reports out of the Desktop
- give the combined component builder a stable place to read from

Recommended subfolders:
- `wpengine-standalone-plugin/`

Examples:
- `reports/runtime/wpengine-standalone-plugin/WP_Engine_Standalone_Plugin_Audit.chunk-1.report-summary.rerun.v1.csv`
- `reports/runtime/wpengine-standalone-plugin/WP_Engine_Standalone_Plugin_Audit.chunk-1.report-matched-urls.rerun.v1.csv`

Final human-facing audit outputs should still go to Desktop, not here.
