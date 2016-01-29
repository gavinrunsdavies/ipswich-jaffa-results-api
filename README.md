# Ipswich-JAFFA-RC

REST API for the Ipswich JAFFA Running Club results database.

#### Functionality
This functionality is only accessible to valid Ipswich JAFFA Wordpress users with the right levels user role capabilities. The following CRUD operations are supported.

  * Results
    * Read - by Event, From Date, To Date
    * Create
    * Delete
	* Update -
	  * Grand Prix status
	  * Finish Position
	  * Race Time
	  * Additional Information
	  * Race Date	
  * Events
    * Read
    * Create
    * Delete (only those with out results)
	* Update -
	  * Name
	  * Website URL	  
	* Merge
  * Races
    * Read - By Id or by EventId
    * Create
  * Event Courses
    * Read
    * Create
    * Delete (only those with out results)
	* Update -
	  * Course Number
	  * County
      * Area
      * Registered Distance	  
	  * Certified Accurate
	  * Type
  * Runners
    * Read
    * Create
    * Delete (only those with out results)
	* Update -
	  * Name
	  * Current Member Status  	
  * Runner of the Month   
    * Create 
  * Distances
    * Read 
  * Sex
    * Read 
  * Course Type
    * Read 
  * Statistics
    * Read
      * Count of results by year
      * Count of results by year and county
      * Count of results by year and country
	  * Count of member Personal Best results
	  * Count of member Personal Bests results by year
	  * Top attended races
	  * Top members racing
	  * Top members racing by year
