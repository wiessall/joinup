-- Add proper permissions for the SPARQL endpoint.
-- See:
--   * https://github.com/openlink/virtuoso-opensource/issues/1201
--   * https://community.openlinksw.com/t/virtuoso-user-rdf-graph-level-access-to-rdf-quad-store/4147

-- User 'SPARQL' should be always single-quoted in this function.
DB.DBA.RDF_DEFAULT_USER_PERMS_SET('SPARQL', 7, 0);
-- User "SPARQL" should be always double-quoted in this statement.
GRANT SPARQL_UPDATE TO "SPARQL";

-- Create a user with read/only access.
DB.DBA.USER_CREATE('SPARQL_RO', 'readonly');
GRANT SPARQL_SELECT to "SPARQL_RO";
