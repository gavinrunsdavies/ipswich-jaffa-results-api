# Ipswich-JAFFA-RC

REST API for the Ipswich JAFFA Running Club results database.

#### Functionality
This functionality is only accessible to valid Ipswich JAFFA Wordpress users with the right levels user role capabilities. The following CRUD operations are supported.

  * Results
    * Read - by Event, From Date, To Date
    * Create
    * Delete
	* Update -
	  * Finish Position
	  * Race Time
	  * Additional Information
	  * Associated Race	
	  * Associated Event
	  * Team Information
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
    * Update - 
    	* Event
    	* Description 
    	* Course Type
    	* Couse Number
    	* County
    	* Country
    	* Area
    	* Conditions
    	* Venue
    	* Grand Prix Status
    * Delete (only those with out results)
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
  * Meetings
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
