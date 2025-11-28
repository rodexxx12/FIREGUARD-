<?php
/**
 * Secure Query Builder for Dynamic WHERE Clauses
 * Prevents SQL injection by using parameterized queries
 */
class SecureQueryBuilder {
    private $conditions = [];
    private $params = [];
    private $paramTypes = [];
    private $paramCounter = 0;
    
    /**
     * Add a condition with optional parameter
     * 
     * @param string $condition SQL condition with ? placeholder
     * @param mixed $value Parameter value (null for static conditions)
     * @param int $type PDO parameter type
     * @return self
     */
    public function addCondition($condition, $value = null, $type = PDO::PARAM_STR) {
        if ($value !== null) {
            $this->paramCounter++;
            $placeholder = 'param_' . $this->paramCounter;
            $this->conditions[] = str_replace('?', ':' . $placeholder, $condition);
            $this->params[$placeholder] = $value;
            $this->paramTypes[$placeholder] = $type;
        } else {
            $this->conditions[] = $condition;
        }
        return $this;
    }
    
    /**
     * Build the final query with WHERE clause
     * 
     * @param string $baseQuery Base SQL query
     * @return array ['query' => string, 'params' => array, 'types' => array]
     */
    public function build($baseQuery) {
        if (empty($this->conditions)) {
            return ['query' => $baseQuery, 'params' => [], 'types' => []];
        }
        
        $whereClause = implode(' AND ', $this->conditions);
        $query = $baseQuery . ' WHERE ' . $whereClause;
        
        return [
            'query' => $query,
            'params' => $this->params,
            'types' => $this->paramTypes
        ];
    }
    
    /**
     * Bind parameters to PDO statement
     * 
     * @param PDOStatement $stmt Prepared statement
     * @return PDOStatement
     */
    public function bindToStatement($stmt) {
        foreach ($this->params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, $this->paramTypes[$key]);
        }
        return $stmt;
    }
    
    /**
     * Reset builder for reuse
     */
    public function reset() {
        $this->conditions = [];
        $this->params = [];
        $this->paramTypes = [];
        $this->paramCounter = 0;
    }
}








