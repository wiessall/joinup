TODO: Completely rewrite the RDF/SPARQL part of docs in ISAICP-8376

What is SPARQL?
---------------
SPARQL is a query language for RDF. It is used to query data stored in a
triplestore. The triplestore used in this project is
[Virtuoso](http://virtuoso.openlinksw.com/).

SPARQL is a standardised language, and is used by many triplestores.
[This page](https://www.w3.org/TR/sparql11-query/) gives a good overview of
the language.

The SPARQL endpoint for this project is at `http://web:8890/sparql`.

SPARQL Queries
--------------

Similar to MySQL, SPARQL queries are written in a SQL-like syntax. The
following is a simple example of a SPARQL query:

    SELECT * WHERE {
      ?s ?p ?o .
    }

The above query will return all the data in the triplestore.

The `SELECT` keyword is used to specify the type of query. In this case, we
are using `SELECT *` to return all the data in the triplestore.

The `WHERE` keyword is used to specify the conditions of the query. In this
case, we are using `?s ?p ?o .` to return all the data in the triplestore.

The `?s`, `?p` and `?o` are variables. They can be used to specify the
subject, predicate and object of the query. `?s` is short for `?subject`,
`?p` is short for `?predicate` and `?o` is short for `?object`. A triple is
made up of a subject, predicate and object - this is the basic unit of data
in RDF. The triple represents a statement about the subject. For example, the
triple `?s ?p ?o` could be used to represent the statement "The subject is
the object". For our project, it is easier to consider the ?s as the entity ID,
the ?p as the field URI and the ?o as the value. The field URI is the mapping
of a field name to a URI. For example, the field URI for the field "Title" is
`http://purl.org/dc/terms/title`. The field URI is used to identify the field
in the triplestore.

The `.` is used to separate the conditions of the query.

Sample queries
--------------

Below are some sample queries that can be run against the triplestore.

### Get all the data

    SELECT * WHERE {
      ?s ?p ?o .
    }

### Get all the data for a specific subject

    SELECT * WHERE {
      <http://example.com/subject> ?p ?o .
    }

You can find the URI ID of the entities in Interoperable Europe Portal by visitin the menu
"Metadata > Export".

### Get all predicates

    SELECT DISTINCT ?p WHERE {
      ?s ?p ?o .
    }

### Get all the data for a specific predicate

    SELECT * WHERE {
      ?s <http://example.com/predicate> ?o .
    }

### Get all available graphs

    SELECT DISTINCT ?g WHERE {
      GRAPH ?g {
        ?s ?p ?o .
      }
    }

### Get all the data for a specific graph

    SELECT * WHERE {
      GRAPH <http://example.com/graph> {
        ?s ?p ?o .
      }
    }

### Get all the data for a specific subject and predicate

      SELECT * WHERE {
        <http://example.com/subject> <http://example.com/predicate> ?o .
      }

### Get all the data for a specific subject and predicate in a specific graph

      SELECT * WHERE {
        GRAPH <http://example.com/graph> {
          <http://example.com/subject> <http://example.com/predicate> ?o .
        }


### Get entities with a specific title

    SELECT DISTINCT ?s WHERE {
      ?s <http://purl.org/dc/terms/title> ?o .
      FILTER regex(?o, "My title", "i")
    }

### Get entities with a specific title and a specific field

    SELECT DISTINCT ?s WHERE {
      ?s <http://purl.org/dc/terms/title> ?o .
      FILTER regex(?o, "My title", "i")
      ?s <http://example.com/predicate> ?o .
    }

### Get entities that reference a specific entity through some field mapping

    SELECT DISTINCT ?s WHERE {
      ?s <http://example.com/predicate> <http://example.com/subject> .
    }

SPARQL Mappings
---------------

In Interoperable Europe Portal, we use `sparql_entity_storage` to map the RDF data to Drupal
entities. The way this is done is through the SPARQL mapping config entities and
the field third party settings.

And example for the SPARQL mapping entity files is the collection mapping. The
collection mapping is defined in the file
`config/install/sparql_entity_storage.mapping.rdf_entity.collection.yml`.

The collection mapping defines the following - might be outdated but still
a valid example:
```yaml
third_party_settings:
  rdf_schema_field_validation:
    property_predicates:
      - 'http://www.w3.org/2000/01/rdf-schema#domain'
    graph: 'http://adms-definition'
    class: 'http://www.w3.org/2000/01/rdf-schema#Class'
```
A list of properties that are used to describe the class of the entity. This is
used for the field validation.

```yaml
rdf_type: 'http://www.w3.org/ns/dcat#Catalog'
```
The RDF type of the entity.

This is the URI mapped to the bundle of the entity. You can use it for example
to find the entities of a specific bundle. For example, for collections, the
following query will return all the collections:
```sparql
SELECT * WHERE {
  ?s <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/ns/dcat#Catalog> .
}
```

```yaml
graph:
  default: 'http://joinup.eu/collection/published'
  draft: 'http://joinup.eu/collection/draft'
```
The graph where the data is stored. The `default` graph is the graph where the
published data is stored. The `draft` graph is the graph where the draft data
is stored.

The graphs are mainly groupings of triples. A good way to understand this is to
think of the graphs as the tables in a relational database. The triples are the
rows in the tables. The graphs are used to separate the data. In Interoperable Europe Portal, we use
graphs to distinguish between bundles and between published and draft data.

For example, in order to retrieve all collection URI IDs that are published, the
following query can be used:
```sparql
SELECT * WHERE {
  GRAPH <http://joinup.eu/collection/published> {
    ?s <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/ns/dcat#Catalog> .
  }
}
```

```yaml
base_fields_mapping:
  rid:
    target_id:
      predicate: 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'
      format: resource
  uid:
    target_id:
      predicate: 'http://joinup.eu/owner/uid'
      format: 'xsd:integer'
  label:
    value:
      predicate: 'http://purl.org/dc/terms/title'
      format: t_literal
  created:
    value:
      predicate: 'http://purl.org/dc/terms/issued'
      format: 'xsd:dateTime'
  changed:
    value:
      predicate: 'http://purl.org/dc/terms/modified'
      format: 'xsd:dateTime'
  uuid:
    value:
      predicate: ''
      format: ''
  langcode:
    value:
      predicate: 'http://joinup.eu/language'
      format: t_literal
  default_langcode:
    value:
      predicate: 'http://joinup.eu/language/default'
      format: literal
  content_translation_source:
    value:
      predicate: 'http://joinup.eu/language/translation_source'
      format: t_literal
  content_translation_outdated:
    value:
      predicate: 'http://joinup.eu/language/translation_outdated'
      format: t_literal
  content_translation_uid:
    target_id:
      predicate: 'http://joinup.eu/language/translation_author'
      format: t_literal
  content_translation_status:
    value:
      predicate: 'http://joinup.eu/language/translation_status'
      format: t_literal
  content_translation_created:
    value:
      predicate: 'http://joinup.eu/language/translation_created_time'
      format: t_literal
  content_translation_changed:
    value:
      predicate: 'http://joinup.eu/language/translation_changed_time'
      format: t_literal
  graph:
    value:
      predicate: ''
      format: ''
```
These are the base fields of the entity. The base fields are the fields that
are defined in the `RdfEntity` class. As you can see, the `rid` field is mapped
to the `http://www.w3.org/1999/02/22-rdf-syntax-ns#type` predicate and all the
other fields are mapped to the predicates that are defined in the Interoperable Europe Portal.

Properties without a mapping value will not be stored in the database. For
example, the uuid field will not be stored to the database according to the
above YAML file. This is the source of the mappings sthat can be used as
predicates in your queries for the database. For example, if you want to find
collections, with the title set to 'My title', you will need the mappings for
the `rid` and the `label` fields. The `rid` field is mapped to the
`http://www.w3.org/1999/02/22-rdf-syntax-ns#type` predicate and the `label`
field is mapped to the `http://purl.org/dc/terms/title` predicate. So, the
following query will return all the collections with the title 'My title':
```sparql
SELECT * WHERE {
  GRAPH <http://joinup.eu/collection/published> {
    ?s <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/ns/dcat#Catalog> .
    ?s <http://purl.org/dc/terms/title> "My title" .
  }
}
```

```yaml
entity_id_plugin: joinup_po_namespace
```
The plugin that is used to generate the entity ID. The entity ID is the URI of
the entity. `joinup_po_namespace` is the plugin that is used to generate the
entity ID in Interoperable Europe Portal.
